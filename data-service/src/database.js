import mongoose from 'mongoose';
import { config } from './config.js';
import { Counter } from './models/Counter.js';
import { User } from './models/User.js';
import { Note } from './models/Note.js';
import { Post } from './models/Post.js';
import { Role } from './models/Role.js';

/** Opens the shared Mongoose connection and ensures local indexes are ready. */
export async function connectDatabase() {
  await mongoose.connect(config.MONGODB_URI, {
    autoIndex: config.NODE_ENV !== 'production',
    serverSelectionTimeoutMS: 5000,
  });
  if (config.NODE_ENV !== 'production') {
    await Promise.all([User.init(), Note.init(), Post.init(), Role.init()]);
  }
  await Counter.updateOne({ _id: 'username' }, { $setOnInsert: { value: 110000 } }, { upsert: true });
  return mongoose.connection;
}

/** Closes the shared connection during an orderly service shutdown. */
export async function closeDatabase() {
  await mongoose.disconnect();
}
