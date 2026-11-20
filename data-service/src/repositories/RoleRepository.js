import mongoose from 'mongoose';
import { Role } from '../models/Role.js';
import { User } from '../models/User.js';
import { ServiceError } from '../errors.js';
import { formatDocument, pageResult } from './format.js';

/** Persists custom roles and prevents deletion while a role is assigned. */
export class RoleRepository {
  async create(fields) {
    return formatDocument(await Role.create(fields));
  }

  async findById(id) {
    return formatDocument(await Role.findById(id).lean());
  }

  async list(page, perPage) {
    const [roles, total] = await Promise.all([
      Role.find().sort({ _id: -1 }).skip((page - 1) * perPage).limit(perPage).lean(),
      Role.countDocuments(),
    ]);
    return pageResult(roles.map(formatDocument), total, page, perPage);
  }

  async update(id, fields) {
    return formatDocument(await Role.findByIdAndUpdate(id, { $set: fields }, {
      returnDocument: 'after', runValidators: true,
    }).lean());
  }

  async delete(id) {
    await mongoose.connection.transaction(async (session) => {
      const assigned = await User.exists({ roleId: id }).session(session);
      if (assigned) throw new ServiceError('ROLE_IN_USE', 'Remove this role from users before deleting it.', 409);
      const result = await Role.deleteOne({ _id: id }).session(session);
      if (result.deletedCount === 0) throw new ServiceError('NOT_FOUND', 'Role not found.', 404);
    });
  }
}
