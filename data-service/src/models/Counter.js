import mongoose, { Schema } from 'mongoose';

const counterSchema = new Schema({
  _id: { type: String, required: true },
  value: { type: Number, required: true, min: 110000 },
}, { versionKey: false });

/** Stores the last issued username without adding another secondary index. */
export const Counter = mongoose.model('Counter', counterSchema);
