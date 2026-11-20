import { z } from 'zod';
import { ServiceError } from './errors.js';
import { permissionKeys } from './permissions.js';

export const objectId = z.string().regex(/^[a-f0-9]{24}$/);
export const email = z.email().max(254).transform((value) => value.toLowerCase());
export const username = z.string().regex(/^[1-9][0-9]{5,15}$/).refine((value) => Number(value) >= 110001);
export const userReference = z.union([username, objectId]);
export const interest = z.string().trim().toLowerCase().min(2).max(50).regex(/^[a-z0-9][a-z0-9 -]*$/);
export const interests = z.array(interest).max(20).refine((values) => new Set(values).size === values.length);
export const roleName = z.string().trim().toLowerCase().min(2).max(50)
  .regex(/^[a-z0-9][a-z0-9 -]*$/).refine((value) => !['admin', 'user'].includes(value));
export const permissions = z.array(z.enum(permissionKeys)).max(permissionKeys.length).refine((values) => new Set(values).size === values.length);
export const roleCreate = z.strictObject({ name: roleName, permissions });
export const roleUpdate = z.strictObject({ name: roleName.optional(), permissions: permissions.optional() })
  .refine((value) => Object.keys(value).length > 0);

const pageNumber = z.string().regex(/^[1-9][0-9]*$/).transform(Number).pipe(z.number().int().max(10000));
const pageSize = z.string().regex(/^[1-9][0-9]*$/).transform(Number).pipe(z.number().int().max(100));
export const pageQuery = z.strictObject({
  page: pageNumber.optional(),
  per_page: pageSize.optional(),
});
export const userCreate = z.strictObject({
  name: z.string().trim().min(2).max(100),
  email,
  passwordHash: z.string().min(60).max(255),
  role: z.enum(['user', 'admin']),
  roleId: objectId.nullable().optional(),
  interests: interests.optional(),
});
export const userUpdate = z.strictObject({
  name: z.string().trim().min(2).max(100).optional(),
  email: email.optional(),
  passwordHash: z.string().min(60).max(255).optional(),
  role: z.enum(['user', 'admin']).optional(),
  roleId: objectId.nullable().optional(),
  interests: interests.optional(),
}).refine((value) => Object.keys(value).length > 0);
export const noteCreate = z.strictObject({
  ownerId: objectId,
  title: z.string().trim().min(1).max(160),
  body: z.string().min(1).max(20000),
});
export const postCreate = z.strictObject({
  authorId: objectId,
  title: z.string().trim().min(1).max(160),
  body: z.string().min(1).max(20000),
});
export const contentUpdate = z.strictObject({
  title: z.string().trim().min(1).max(160).optional(),
  body: z.string().min(1).max(20000).optional(),
}).refine((value) => Object.keys(value).length > 0);

/** Applies one strict Zod contract at each private route boundary. */
export function validate(schema, value) {
  const result = schema.safeParse(value);
  if (!result.success) {
    throw new ServiceError('VALIDATION_FAILED', 'The request is invalid.', 422);
  }
  return result.data;
}

export function pagination(query) {
  const value = validate(pageQuery, query);
  return { page: value.page ?? 1, perPage: value.per_page ?? 20 };
}
