import mongoose, { Schema } from 'mongoose';

const noteSchema = new Schema({
  ownerId: { type: Schema.Types.ObjectId, required: true, ref: 'User' },
  title: { type: String, required: true, trim: true },
  body: { type: String, required: true },
}, { timestamps: true, versionKey: false, strict: 'throw' });

// The implicit _id index serves direct reads and admin lists; this one serves owner lists.
noteSchema.index({ ownerId: 1, _id: -1 });

export const Note = mongoose.model('Note', noteSchema);
