import mongoose, { Schema } from 'mongoose';
import { permissionKeys } from '../permissions.js';

const roleSchema = new Schema({
  name: { type: String, required: true, lowercase: true, trim: true },
  permissions: { type: [{ type: String, enum: permissionKeys }], default: [] },
}, { timestamps: true, versionKey: false, strict: 'throw' });

roleSchema.index({ name: 1 }, { unique: true });

export const Role = mongoose.model('Role', roleSchema);
