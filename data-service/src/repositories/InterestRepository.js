import { User } from '../models/User.js';
import { formatDocument, pageResult } from './format.js';

/** Provides the required one-call grouping view and indexed member pages. */
export class InterestRepository {
  /** The grouping view executes exactly one collection.aggregate() call. */
  async groups(page, perPage) {
    const [result] = await User.collection.aggregate([
      { $unwind: '$interests' },
      { $group: { _id: '$interests', memberCount: { $sum: 1 } } },
      { $sort: { _id: 1 } },
      { $facet: {
        items: [
          { $skip: (page - 1) * perPage },
          { $limit: perPage },
          { $lookup: {
            from: User.collection.name,
            localField: '_id',
            foreignField: 'interests',
            pipeline: [
              { $sort: { _id: -1 } },
              { $limit: 5 },
              { $project: { username: 1, name: 1, email: 1 } },
            ],
            as: 'members',
          } },
        ],
        meta: [{ $count: 'total' }],
      } },
    ], { allowDiskUse: true, maxTimeMS: 5000 }).toArray();

    const groups = (result?.items ?? []).map((group) => ({
      interest: group._id,
      memberCount: group.memberCount,
      members: group.members.map(formatDocument),
    }));
    return pageResult(groups, result?.meta[0]?.total ?? 0, page, perPage);
  }

  async members(interest, page, perPage) {
    const filter = { interests: interest };
    const [users, total] = await Promise.all([
      User.find(filter).sort({ _id: -1 }).skip((page - 1) * perPage).limit(perPage).lean(),
      User.countDocuments(filter),
    ]);
    return pageResult(users.map(formatDocument), total, page, perPage);
  }
}
