"""Exercise the real Docker API, aggregation views, and ownership boundaries."""

import json
import secrets
import subprocess
import urllib.error
import urllib.request
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
BASE = "http://127.0.0.1:8000/api"


def call(method, path, body=None, token=None):
    headers = {"Accept": "application/json"}
    if body is not None:
        headers["Content-Type"] = "application/json"
    if token:
        headers["Authorization"] = f"Bearer {token}"
    request = urllib.request.Request(
        BASE + path,
        data=json.dumps(body).encode() if body is not None else None,
        headers=headers,
        method=method,
    )
    try:
        response = urllib.request.urlopen(request, timeout=10)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        payload = response.read()
        return response.status, json.loads(payload) if payload else None


def expect(status, result, expected):
    assert status == expected, f"Expected {expected}, received {status}: {result}"
    return result


def remove_test_account(user_id):
    script = (
        "import {connectDatabase,closeDatabase} from './src/database.js';"
        "import {UserService} from './src/services/UserService.js';"
        "await connectDatabase();"
        "try {await new UserService().deleteWithContent(process.argv[1]);}"
        "catch(error) {if(error.code !== 'NOT_FOUND') throw error;}"
        "finally {await closeDatabase();}"
    )
    subprocess.run(
        ["docker", "compose", "exec", "-T", "data-service", "node", "--input-type=module", "-e", script, user_id],
        cwd=ROOT,
        check=True,
        stdout=subprocess.DEVNULL,
    )


def database_snapshot():
    script = (
        "import {connectDatabase,closeDatabase} from './src/database.js';"
        "import {Counter} from './src/models/Counter.js';"
        "import {User} from './src/models/User.js';"
        "import {Note} from './src/models/Note.js';"
        "import {Post} from './src/models/Post.js';"
        "import {Role} from './src/models/Role.js';"
        "await connectDatabase();"
        "try {const counter=await Counter.findById('username').lean();"
        "console.log(JSON.stringify({users:await User.countDocuments(),notes:await Note.countDocuments(),"
        "posts:await Post.countDocuments(),roles:await Role.countDocuments(),last:counter.value}));}"
        "finally {await closeDatabase();}"
    )
    result = subprocess.run(
        ["docker", "compose", "exec", "-T", "data-service", "node", "--input-type=module", "-e", script],
        cwd=ROOT, check=True, capture_output=True, text=True,
    )
    return json.loads(result.stdout)


def restore_username_counter(snapshot, created_count):
    script = (
        "import mongoose from 'mongoose';"
        "import {connectDatabase,closeDatabase} from './src/database.js';"
        "import {Counter} from './src/models/Counter.js';"
        "import {User} from './src/models/User.js';"
        "import {Note} from './src/models/Note.js';"
        "import {Post} from './src/models/Post.js';"
        "import {Role} from './src/models/Role.js';"
        "await connectDatabase();"
        "try {await mongoose.connection.transaction(async session=>{"
        "const users=await User.countDocuments().session(session);"
        "const notes=await Note.countDocuments().session(session);"
        "const posts=await Post.countDocuments().session(session);"
        "const roles=await Role.countDocuments().session(session);"
        "const counter=await Counter.findById('username').session(session).lean();"
        "const before=JSON.parse(process.argv[1]);"
        "if(users!==before.users||notes!==before.notes||posts!==before.posts||roles!==before.roles||"
        "counter.value!==before.last+Number(process.argv[2]))"
        "throw new Error('Database changed; test counter was preserved');"
        "await Counter.updateOne({_id:'username',value:counter.value},{$set:{value:before.last}},{session});"
        "});} finally {await closeDatabase();}"
    )
    subprocess.run(
        ["docker", "compose", "exec", "-T", "data-service", "node", "--input-type=module", "-e", script,
         json.dumps(snapshot), str(created_count)],
        cwd=ROOT, check=True, stdout=subprocess.DEVNULL,
    )


def main():
    snapshot = database_snapshot()
    suffix = secrets.token_hex(5)
    password = secrets.token_hex(3)  # Exactly six characters exercises the configured minimum.
    created_count = 0
    admin_id = None
    member_id = None
    extra_id = None
    later_id = None
    role_id = None
    admin_token = None
    try:
        expect(*call("POST", "/auth/register", {
            "name": "Mismatched Password", "email": f"mismatch-{suffix}@example.test",
            "password": password, "confirmPassword": password + "x",
        }), 422)
        expect(*call("POST", "/auth/register", {
            "name": "Bad Email", "email": "invalid-email",
            "password": password, "confirmPassword": password,
        }), 422)
        status, admin = call("POST", "/auth/register", {
            "name": "Admin Candidate", "email": f"admin-{suffix}@example.test",
            "password": password, "confirmPassword": password, "interests": ["chess", "reading"],
        })
        admin = expect(status, admin, 201)
        created_count += 1
        admin_id = admin["user"]["id"]
        assert admin["user"]["role"] == "user"
        first_username = int(admin["user"]["username"])
        assert first_username >= 110001
        if snapshot["users"] == 0:
            assert first_username == 110001
        assert "passwordHash" not in admin["user"]

        expect(*call("POST", "/auth/register", {
            "name": "Duplicate Account", "email": f"admin-{suffix}@example.test",
            "password": password, "confirmPassword": password,
        }), 409)

        status, member = call("POST", "/auth/register", {
            "name": "Test Member", "email": f"member-{suffix}@example.test",
            "password": password, "confirmPassword": password, "interests": ["chess"],
        })
        member = expect(status, member, 201)
        created_count += 1
        member_id = member["user"]["id"]
        assert int(member["user"]["username"]) == first_username + 1
        member_token = member["token"]
        admin_token = admin["token"]

        expect(*call("POST", "/auth/login", {
            "email": f"member-{suffix}@example.test", "password": password + "wrong",
        }), 401)
        username_login = expect(*call("POST", "/auth/login", {
            "username": member["user"]["username"], "password": password,
        }), 200)
        assert username_login["user"]["id"] == member_id
        expect(*call("POST", "/auth/login", {
            "email": f"member-{suffix}@example.test",
            "username": member["user"]["username"], "password": password,
        }), 422)

        subprocess.run(
            ["docker", "compose", "exec", "-T", "api", "php", "bin/promote-admin.php", f"admin-{suffix}@example.test"],
            cwd=ROOT, check=True, stdout=subprocess.DEVNULL,
        )
        assert expect(*call("GET", "/auth/me", token=admin_token), 200)["role"] == "admin"

        extra = expect(*call("POST", "/admin/users", {
            "name": "Admin Created", "email": f"extra-{suffix}@example.test",
            "password": password, "confirmPassword": password, "interests": [],
        }, admin_token), 201)
        created_count += 1
        extra_id = extra["id"]
        assert int(extra["username"]) == first_username + 2
        changed_extra = expect(*call("PATCH", f"/admin/users/{extra_id}", {
            "name": "Renamed Admin Created",
        }, admin_token), 200)
        assert changed_extra["username"] == extra["username"]
        expect(*call("PATCH", f"/admin/users/{extra_id}", {
            "password": password,
        }, admin_token), 422)
        expect(*call("PATCH", f"/admin/users/{extra_id}", {
            "password": password, "confirmPassword": password + "x",
        }, admin_token), 422)
        expect(*call("PATCH", f"/admin/users/{extra_id}", {
            "password": password, "confirmPassword": password,
        }, admin_token), 200)
        expect(*call("DELETE", f"/admin/users/{extra_id}", token=admin_token), 204)
        extra_id = None

        later = expect(*call("POST", "/auth/register", {
            "name": "Later Account", "email": f"later-{suffix}@example.test",
            "password": password, "confirmPassword": password,
        }), 201)
        created_count += 1
        later_id = later["user"]["id"]
        assert int(later["user"]["username"]) == first_username + 3

        member_note = expect(*call("POST", "/notes", {
            "title": "Private member note", "body": "Only its owner may edit it.",
        }, member_token), 201)
        admin_note = expect(*call("POST", "/notes", {
            "title": "Admin note", "body": "Visible in the admin list.",
        }, admin_token), 201)
        assert expect(*call("GET", "/notes", token=member_token), 200)["total"] == 1
        expect(*call("GET", "/notes?scope=all", token=member_token), 403)
        assert expect(*call("GET", "/notes?scope=all", token=admin_token), 200)["total"] >= 2
        expect(*call("GET", f"/notes/{admin_note['id']}", token=member_token), 404)
        expect(*call("PATCH", f"/notes/{member_note['id']}", {"title": "Forbidden"}, admin_token), 403)
        expect(*call("PATCH", f"/notes/{member_note['id']}", {"title": "Updated"}, member_token), 200)

        member_post = expect(*call("POST", "/posts", {
            "title": "Public member post", "body": "Anyone may read this.",
        }, member_token), 201)
        expect(*call("POST", "/posts", {
            "title": "Public admin post", "body": "Also public.",
        }, admin_token), 201)
        assert expect(*call("GET", "/posts"), 200)["total"] >= 2
        by_user = expect(*call("GET", f"/users/{member_id}/posts?page=1&per_page=10"), 200)
        assert by_user["total"] == 1 and by_user["items"][0]["id"] == member_post["id"]
        by_username = expect(*call("GET", f"/users/{member['user']['username']}/posts?page=1&per_page=10"), 200)
        assert by_username["items"][0]["id"] == member_post["id"]
        assert by_username["items"][0]["authorUsername"] == member["user"]["username"]
        expect(*call("PATCH", f"/posts/{member_post['id']}", {"title": "Forbidden"}, admin_token), 403)

        groups = expect(*call("GET", "/admin/interests", token=admin_token), 200)
        chess = next(group for group in groups["items"] if group["interest"] == "chess")
        assert chess["memberCount"] >= 2 and len(chess["members"]) >= 2
        assert expect(*call("GET", "/admin/interests/chess/users", token=admin_token), 200)["total"] >= 2
        expect(*call("GET", "/admin/interests", token=member_token), 403)

        assert expect(*call("GET", "/admin/users?page=1&per_page=1", token=admin_token), 200)["perPage"] == 1
        updated = expect(*call("PATCH", f"/admin/users/{member_id}", {"name": "Renamed Member"}, admin_token), 200)
        assert updated["interests"] == ["chess"]
        expect(*call("DELETE", f"/admin/users/{admin_id}", token=admin_token), 403)

        catalog = expect(*call("GET", "/admin/permissions", token=admin_token), 200)
        assert any(item["key"] == "users.manage" for item in catalog["items"])
        expect(*call("GET", "/admin/permissions", token=member_token), 403)
        expect(*call("POST", "/admin/roles", {"name": "admin", "permissions": []}, admin_token), 422)
        role = expect(*call("POST", "/admin/roles", {
            "name": f"reviewer {suffix}", "permissions": ["notes.read"],
        }, admin_token), 201)
        role_id = role["id"]
        assert expect(*call("GET", "/admin/roles", token=admin_token), 200)["total"] >= 1
        expect(*call("POST", "/admin/roles", {"name": "denied", "permissions": []}, member_token), 403)
        expect(*call("PATCH", f"/admin/users/{member_id}", {
            "role": "user", "roleId": role_id,
        }, member_token), 403)
        expect(*call("PATCH", f"/admin/users/{member_id}", {
            "role": "user", "roleId": role_id,
        }, admin_token), 200)
        me = expect(*call("GET", "/auth/me", token=member_token), 200)
        assert me["roleName"] == role["name"] and me["permissions"] == ["notes.read"]
        expect(*call("POST", "/notes", {"title": "Denied", "body": "No write permission"}, member_token), 403)
        expect(*call("POST", "/posts", {"title": "Denied", "body": "No write permission"}, member_token), 403)
        expect(*call("DELETE", f"/admin/roles/{role_id}", token=admin_token), 409)
        expect(*call("PATCH", f"/admin/roles/{role_id}", {
            "permissions": ["notes.read", "users.manage"],
        }, admin_token), 200)
        assert "users.manage" in expect(*call("GET", "/auth/me", token=member_token), 200)["permissions"]
        expect(*call("GET", "/admin/users", token=member_token), 200)
        expect(*call("PATCH", f"/admin/users/{admin_id}", {"name": "Denied"}, member_token), 403)
        expect(*call("DELETE", f"/admin/users/{admin_id}", token=member_token), 403)
        expect(*call("POST", "/admin/roles", {"name": "denied", "permissions": []}, member_token), 403)
        expect(*call("PATCH", f"/admin/roles/{role_id}", {"permissions": []}, admin_token), 200)
        expect(*call("GET", "/admin/users", token=member_token), 403)

        expect(*call("DELETE", f"/admin/users/{member_id}", token=admin_token), 204)
        member_id = None
        expect(*call("DELETE", f"/admin/roles/{role_id}", token=admin_token), 204)
        role_id = None
        expect(*call("GET", "/auth/me", token=member_token), 401)
        expect(*call("GET", f"/users/{member['user']['id']}/posts"), 404)
        expect(*call("GET", f"/notes/{member_note['id']}", token=admin_token), 404)
        print("Docker end-to-end features passed.")
    finally:
        for user_id in (member_id, extra_id, later_id, admin_id):
            if user_id:
                remove_test_account(user_id)
        if role_id and admin_token:
            expect(*call("DELETE", f"/admin/roles/{role_id}", token=admin_token), 204)
        if created_count:
            restore_username_counter(snapshot, created_count)


if __name__ == "__main__":
    main()
