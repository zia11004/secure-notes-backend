#!/usr/bin/env python3
"""Create matching local secrets without printing or overwriting them."""

import os
import secrets
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
TARGETS = (ROOT / '.env', ROOT / 'api/.env', ROOT / 'data-service/.env')


def render(template: Path, replacements: dict[str, str]) -> str:
    content = template.read_text(encoding='utf-8')
    for old, new in replacements.items():
        if content.count(old) != 1:
            raise ValueError(f'Expected exactly one {old!r} in {template.name}')
        content = content.replace(old, new)
    return content


def write_private(path: Path, content: str) -> None:
    descriptor = os.open(path, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
    with os.fdopen(descriptor, 'w', encoding='utf-8') as stream:
        stream.write(content)


def main() -> None:
    existing = [path for path in TARGETS if path.exists()]
    if len(existing) == len(TARGETS):
        print('Environment files already exist; nothing changed.')
        return
    if existing:
        raise SystemExit('Partial environment setup found. Complete the missing files manually; existing secrets were not changed.')

    service_token = secrets.token_hex(32)
    content = (
        render(ROOT / '.env.example', {
            'replace-with-a-random-hex-password': secrets.token_hex(32),
            'replace-with-a-different-random-hex-password': secrets.token_hex(32),
        }),
        render(ROOT / 'api/.env.example', {
            'replace-with-a-random-secret-of-at-least-32-characters': secrets.token_hex(32),
            'replace-with-a-different-random-secret-of-at-least-32-characters': service_token,
        }),
        render(ROOT / 'data-service/.env.example', {
            'replace-with-the-same-random-token-used-by-the-PHP-API': service_token,
        }),
    )
    for path, value in zip(TARGETS, content):
        write_private(path, value)
    print('Created private environment files for Docker. Secrets were not displayed.')


if __name__ == '__main__':
    main()
