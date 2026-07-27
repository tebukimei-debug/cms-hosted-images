import { Worker, Job } from "bullmq";
import Redis from "ioredis";
import sharp from "sharp";
import { S3Client, GetObjectCommand, PutObjectCommand } from "@aws-sdk/client-s3";
import { PrismaClient } from "@prisma/client";

const prisma = new PrismaClient();
const connection = new Redis(process.env.REDIS_URL || "redis://localhost:6379");

const s3Client = new S3Client({
  region: process.env.S3_REGION || "us-east-1",
  endpoint: process.env.S3_ENDPOINT || "http://localhost:9000",
  credentials: {
    accessKeyId: process.env.S3_ACCESS_KEY || "admin",
    secretAccessKey: process.env.S3_SECRET_KEY || "password123",
  },
  forcePathStyle: true,
});
const bucket = process.env.S3_BUCKET || "chevereto";

// Stream to buffer helper
const streamToBuffer = async (stream: any): Promise<Buffer> => {
  const chunks: any[] = [];
  for await (const chunk of stream) chunks.push(chunk);
  return Buffer.concat(chunks);
};

const worker = new Worker("image-processing", async (job: Job) => {
  const { imageId } = job.data;
  console.log(`Processing image ${imageId}...`);

  const imageRecord = await prisma.image.findUnique({ where: { id: imageId } });
  if (!imageRecord) throw new Error("Image not found");

  // Fetch original from S3
  const getCmd = new GetObjectCommand({ Bucket: bucket, Key: imageRecord.storageKey });
  const s3Obj = await s3Client.send(getCmd);
  const buffer = await streamToBuffer(s3Obj.Body);

  // Process with Sharp (Strip EXIF by default when resizing, add watermark if needed)
  const image = sharp(buffer);
  
  // 1. Generate Thumbnail
  const thumbBuffer = await image.clone()
    .resize({ width: 300, withoutEnlargement: true })
    .jpeg({ quality: 80 })
    .toBuffer();

  const thumbKey = `thumbs/${imageId}.jpg`;
  await s3Client.send(new PutObjectCommand({
    Bucket: bucket,
    Key: thumbKey,
    Body: thumbBuffer,
    ContentType: "image/jpeg",
  }));

  const endpoint = process.env.S3_ENDPOINT || "http://localhost:9000";
  const thumbUrl = `${endpoint}/${bucket}/${thumbKey}`;

  // 2. Hash calculation for duplicate detection
  // In a real app we'd use perceptual hashing. For now we use standard crypto or sharp metadata.
  const hash = Date.now().toString(); // Placeholder hash

  // 3. Update DB
  await prisma.image.update({
    where: { id: imageId },
    data: { 
      thumbUrl,
      hash
    }
  });

  console.log(`Successfully processed image ${imageId}`);
}, { connection });

worker.on("completed", job => {
  console.log(`Job ${job.id} completed!`);
});

worker.on("failed", (job, err) => {
  console.error(`Job ${job?.id} failed:`, err);
});

console.log("Worker started...");
