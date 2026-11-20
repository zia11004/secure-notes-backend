import mongoose from 'mongoose';
import { Counter } from '../models/Counter.js';
import { User } from '../models/User.js';
import { formatDocument, pageResult } from './format.js';

/** Owns indexed user reads and writes; public role decisions stay in PHP. */
export class UserRepository {
  async create(fields) {
    let created;
    // Reserving the next number and inserting the user must commit together.
    await mongoose.connection.transaction(async (session) => {
      const counter = await Counter.findOneAndUpdate(
        { _id: 'username' },
        { $inc: { value: 1 } },
        { session, returnDocument: 'after' },
      ).lean();
      if (!counter) throw new Error('Username counter is not initialized.');
      const [user] = await User.create([{ ...fields, username: String(counter.value) }], { session });
      created = formatDocument(user);
    });
    return created;
  }

  async findById(id) {
    return formatDocument(await User.findById(id).lean());
  }

  async findByEmail(email) {
    const user = await User.findOne({ email }).select('+passwordHash').lean();
    return user ? { ...formatDocument(user), passwordHash: user.passwordHash } : null;
  }

  async findByUsername(username) {
    const user = await User.findOne({ username }).select('+passwordHash').lean();
    return user ? { ...formatDocument(user), passwordHash: user.passwordHash } : null;
  }

  async list(page, perPage) {
    const [users, total] = await Promise.all([
      User.find().sort({ _id: -1 }).skip((page - 1) * perPage).limit(perPage).lean(),
      User.countDocuments(),
    ]);
    return pageResult(users.map(formatDocument), total, page, perPage);
  }

  async update(id, fields) {
    return formatDocument(await User.findByIdAndUpdate(id, { $set: fields }, {
      returnDocument: 'after', runValidators: true,
    }).lean());
  }
}
