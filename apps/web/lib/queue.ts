import { Queue } from "bullmq";
import Redis from "ioredis";

// Use a shared Redis connection for the queue
const connection = new Redis(process.env.REDIS_URL || "redis://localhost:6379");

export const imageQueue = new Queue("image-processing", { connection });
