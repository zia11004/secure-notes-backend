import mongoose from 'mongoose';
import { Post } from '../models/Post.js';
import { User } from '../models/User.js';
import { formatDocument, pageResult } from './format.js';

/** Stores public posts and provides the required author $lookup view. */
export class PostRepository {
  async create(fields) {
    return formatDocument(await Post.create(fields));
  }

  async findById(id) {
    return formatDocument(await Post.findById(id).lean());
  }

  async list(page, perPage) {
    const [posts, total] = await Promise.all([
      Post.aggregate([
        { $sort: { _id: -1 } },
        { $skip: (page - 1) * perPage },
        { $limit: perPage },
        { $lookup: {
          from: User.collection.name,
          localField: 'authorId',
          foreignField: '_id',
          pipeline: [{ $project: { username: 1 } }],
          as: 'author',
        } },
        { $set: { authorUsername: { $arrayElemAt: ['$author.username', 0] } } },
        { $unset: 'author' },
      ]).allowDiskUse(true),
      Post.countDocuments(),
    ]);
    return pageResult(posts.map(formatDocument), total, page, perPage);
  }

  async update(id, fields) {
    return formatDocument(await Post.findByIdAndUpdate(id, { $set: fields }, {
      returnDocument: 'after', runValidators: true,
    }).lean());
  }

  async delete(id) {
    return (await Post.deleteOne({ _id: id })).deletedCount > 0;
  }

  /** One pipeline matches the user, joins their indexed posts, and pages the result. */
  async byUser(userReference, page, perPage) {
    const userFilter = /^[a-f0-9]{24}$/.test(userReference)
      ? { _id: new mongoose.Types.ObjectId(userReference) }
      : { username: userReference };
    const [record] = await User.collection.aggregate([
      { $match: userFilter },
      { $lookup: {
        from: Post.collection.name,
        localField: '_id',
        foreignField: 'authorId',
        pipeline: [
          { $sort: { _id: -1 } },
          { $facet: {
            items: [{ $skip: (page - 1) * perPage }, { $limit: perPage }],
            meta: [{ $count: 'total' }],
          } },
        ],
        as: 'joined',
      } },
      { $project: { name: 1, username: 1, joined: 1 } },
    ], { maxTimeMS: 5000 }).toArray();

    if (!record) return null;
    const joined = record.joined[0] ?? { items: [], meta: [] };
    return {
      user: { id: String(record._id), name: record.name, username: record.username },
      ...pageResult(joined.items.map((post) => ({ ...formatDocument(post), authorUsername: record.username })), joined.meta[0]?.total ?? 0, page, perPage),
    };
  }
}
