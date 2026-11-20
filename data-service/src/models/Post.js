import mongoose, { Schema } from 'mongoose';

const postSchema = new Schema({
  authorId: { type: Schema.Types.ObjectId, required: true, ref: 'User' },
  title: { type: String, required: true, trim: true },
  body: { type: String, required: true },
}, { timestamps: true, versionKey: false, strict: 'throw' });

// The implicit _id index serves direct reads and public lists; this one serves author lists.
postSchema.index({ authorId: 1, _id: -1 });

export const Post = mongoose.model('Post', postSchema);
