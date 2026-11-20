import { connectDatabase, closeDatabase } from './database.js';
import { User } from './models/User.js';
import { Note } from './models/Note.js';
import { Post } from './models/Post.js';
import { Role } from './models/Role.js';

await connectDatabase();
try {
  await Promise.all([User.createIndexes(), Note.createIndexes(), Post.createIndexes(), Role.createIndexes()]);
  console.log('Declared indexes are ready.');
} finally {
  await closeDatabase();
}
