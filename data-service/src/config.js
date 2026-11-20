import 'dotenv/config';
import { z } from 'zod';

const schema = z.object({
  NODE_ENV: z.enum(['development', 'test', 'production']).default('development'),
  HOST: z.enum(['127.0.0.1', '0.0.0.0']).default('127.0.0.1'),
  PORT: z.string().regex(/^[1-9][0-9]*$/).transform(Number).pipe(z.number().int().max(65535)).default(3001),
  MONGODB_URI: z.string().url().refine(
    (value) => /^mongodb(\+srv)?:\/\//.test(value) && new URL(value).pathname.length > 1,
    'Must be a MongoDB URI with an explicit database name',
  ),
  INTERNAL_SERVICE_TOKEN: z.string().min(32).regex(/^\S+$/).refine(
    (value) => !value.startsWith('replace-'),
    'Set a random private service token',
  ),
});

const parsed = schema.safeParse(process.env);
if (!parsed.success) {
  throw new Error(`Invalid data-service environment: ${z.prettifyError(parsed.error)}`);
}

export const config = Object.freeze(parsed.data);
