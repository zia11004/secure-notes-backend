import mongoose from 'mongoose';
import { User } from '../models/User.js';
import { Note } from '../models/Note.js';
import { Post } from '../models/Post.js';
import { ServiceError } from '../errors.js';

/** Deletes an account and its private notes and public posts atomically. */
export class UserService {
  async deleteWithContent(id) {
    await mongoose.connection.transaction(async (session) => {
      const user = await User.findById(id).session(session);
      if (!user) throw new ServiceError('NOT_FOUND', 'User not found.', 404);
      await Note.deleteMany({ ownerId: id }).session(session);
      await Post.deleteMany({ authorId: id }).session(session);
      await User.deleteOne({ _id: id }).session(session);
    });
  }
}
