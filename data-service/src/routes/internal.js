import { Router } from 'express';
import { z } from 'zod';
import { ServiceError } from '../errors.js';
import { UserRepository } from '../repositories/UserRepository.js';
import { NoteRepository } from '../repositories/NoteRepository.js';
import { PostRepository } from '../repositories/PostRepository.js';
import { InterestRepository } from '../repositories/InterestRepository.js';
import { RoleRepository } from '../repositories/RoleRepository.js';
import { UserService } from '../services/UserService.js';
import {
  contentUpdate, email, interest, noteCreate, objectId, pageQuery, pagination,
  postCreate, userCreate, userUpdate, userReference, username, validate,
  roleCreate, roleUpdate,
} from '../validation.js';

export const internalRoutes = Router();
const users = new UserRepository();
const notes = new NoteRepository();
const posts = new PostRepository();
const interests = new InterestRepository();
const userService = new UserService();
const roles = new RoleRepository();

function idOf(request) {
  return validate(objectId, request.params.id);
}

function found(value, label) {
  if (!value) throw new ServiceError('NOT_FOUND', `${label} not found.`, 404);
  return value;
}

internalRoutes.post('/users/lookup', async (request, response) => {
  const lookup = validate(z.strictObject({
    email: email.optional(),
    username: username.optional(),
  }).refine((value) => Boolean(value.email) !== Boolean(value.username)), request.body);
  const user = lookup.email
    ? await users.findByEmail(lookup.email)
    : await users.findByUsername(lookup.username);
  response.json(found(user, 'User'));
});
internalRoutes.get('/users', async (request, response) => {
  const { page, perPage } = pagination(request.query);
  response.json(await users.list(page, perPage));
});
internalRoutes.post('/users', async (request, response) => {
  response.status(201).json(await users.create(validate(userCreate, request.body)));
});
internalRoutes.get('/users/:id', async (request, response) => {
  response.json(found(await users.findById(idOf(request)), 'User'));
});
internalRoutes.patch('/users/:id', async (request, response) => {
  response.json(found(await users.update(idOf(request), validate(userUpdate, request.body)), 'User'));
});
internalRoutes.delete('/users/:id', async (request, response) => {
  await userService.deleteWithContent(idOf(request));
  response.status(204).end();
});

internalRoutes.get('/roles', async (request, response) => {
  const { page, perPage } = pagination(request.query);
  response.json(await roles.list(page, perPage));
});
internalRoutes.post('/roles', async (request, response) => {
  response.status(201).json(await roles.create(validate(roleCreate, request.body)));
});
internalRoutes.get('/roles/:id', async (request, response) => {
  response.json(found(await roles.findById(idOf(request)), 'Role'));
});
internalRoutes.patch('/roles/:id', async (request, response) => {
  response.json(found(await roles.update(idOf(request), validate(roleUpdate, request.body)), 'Role'));
});
internalRoutes.delete('/roles/:id', async (request, response) => {
  await roles.delete(idOf(request));
  response.status(204).end();
});

internalRoutes.get('/notes', async (request, response) => {
  const query = validate(pageQuery.extend({ owner_id: objectId.optional() }), request.query);
  response.json(await notes.list(query.owner_id, query.page ?? 1, query.per_page ?? 20));
});
internalRoutes.post('/notes', async (request, response) => {
  response.status(201).json(await notes.create(validate(noteCreate, request.body)));
});
internalRoutes.get('/notes/:id', async (request, response) => {
  response.json(found(await notes.findById(idOf(request)), 'Note'));
});
internalRoutes.patch('/notes/:id', async (request, response) => {
  response.json(found(await notes.update(idOf(request), validate(contentUpdate, request.body)), 'Note'));
});
internalRoutes.delete('/notes/:id', async (request, response) => {
  if (!await notes.delete(idOf(request))) throw new ServiceError('NOT_FOUND', 'Note not found.', 404);
  response.status(204).end();
});

internalRoutes.get('/posts', async (request, response) => {
  const { page, perPage } = pagination(request.query);
  response.json(await posts.list(page, perPage));
});
internalRoutes.post('/posts', async (request, response) => {
  response.status(201).json(await posts.create(validate(postCreate, request.body)));
});
internalRoutes.get('/posts/:id', async (request, response) => {
  response.json(found(await posts.findById(idOf(request)), 'Post'));
});
internalRoutes.patch('/posts/:id', async (request, response) => {
  response.json(found(await posts.update(idOf(request), validate(contentUpdate, request.body)), 'Post'));
});
internalRoutes.delete('/posts/:id', async (request, response) => {
  if (!await posts.delete(idOf(request))) throw new ServiceError('NOT_FOUND', 'Post not found.', 404);
  response.status(204).end();
});
internalRoutes.get('/users/:id/posts', async (request, response) => {
  const { page, perPage } = pagination(request.query);
  const reference = validate(userReference, request.params.id);
  response.json(found(await posts.byUser(reference, page, perPage), 'User'));
});

internalRoutes.get('/interests', async (request, response) => {
  const { page, perPage } = pagination(request.query);
  response.json(await interests.groups(page, perPage));
});
internalRoutes.get('/interests/:interest/users', async (request, response) => {
  const topic = validate(interest, request.params.interest);
  const { page, perPage } = pagination(request.query);
  response.json(await interests.members(topic, page, perPage));
});
