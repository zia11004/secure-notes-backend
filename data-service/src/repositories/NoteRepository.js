import { Note } from '../models/Note.js';
import { formatDocument, pageResult } from './format.js';

/** Uses the owner compound index for private lists and _id for direct reads. */
export class NoteRepository {
  async create(fields) {
    return formatDocument(await Note.create(fields));
  }

  async findById(id) {
    return formatDocument(await Note.findById(id).lean());
  }

  async list(ownerId, page, perPage) {
    const filter = ownerId ? { ownerId } : {};
    const [notes, total] = await Promise.all([
      Note.find(filter).sort({ _id: -1 }).skip((page - 1) * perPage).limit(perPage).populate('ownerId', 'username').lean(),
      Note.countDocuments(filter),
    ]);
    return pageResult(notes.map((note) => {
      const owner = note.ownerId;
      return {
        ...formatDocument({ ...note, ownerId: owner?._id ?? owner }),
        ownerUsername: owner?.username ?? null,
      };
    }), total, page, perPage);
  }

  async update(id, fields) {
    return formatDocument(await Note.findByIdAndUpdate(id, { $set: fields }, {
      returnDocument: 'after', runValidators: true,
    }).lean());
  }

  async delete(id) {
    return (await Note.deleteOne({ _id: id })).deletedCount > 0;
  }
}
