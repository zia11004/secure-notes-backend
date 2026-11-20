import mongoose, { Schema } from 'mongoose';

const userSchema = new Schema({
  username: { type: String, required: true, immutable: true },
  name: { type: String, required: true, trim: true },
  email: { type: String, required: true, lowercase: true, trim: true },
  passwordHash: { type: String, required: true, select: false },
  role: { type: String, required: true, enum: ['user', 'admin'] },
  roleId: { type: Schema.Types.ObjectId, ref: 'Role', default: null },
  interests: { type: [{ type: String, lowercase: true, trim: true }], default: [] },
}, { timestamps: true, versionKey: false, strict: 'throw' });

// Usernames are issued by one counter; the index also protects against duplicate issuance.
userSchema.index({ username: 1 }, { unique: true });
// Email identifies logins; the multikey index supports interest member pages and $lookup.
userSchema.index({ email: 1 }, { unique: true });
userSchema.index({ interests: 1, _id: -1 });
userSchema.index({ roleId: 1 });

export const User = mongoose.model('User', userSchema);
