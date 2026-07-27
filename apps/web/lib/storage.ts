import { S3Client, PutObjectCommand, CreateBucketCommand, HeadBucketCommand } from "@aws-sdk/client-s3";

export const s3Client = new S3Client({
  region: process.env.S3_REGION || "us-east-1",
  endpoint: process.env.S3_ENDPOINT,
  credentials: {
    accessKeyId: process.env.S3_ACCESS_KEY || "",
    secretAccessKey: process.env.S3_SECRET_KEY || "",
  },
  forcePathStyle: true, // Required for MinIO
});

let bucketCreated = false;

async function ensureBucketExists(bucket: string) {
  if (bucketCreated) return;
  try {
    await s3Client.send(new HeadBucketCommand({ Bucket: bucket }));
    bucketCreated = true;
  } catch (err: any) {
    if (err.name === 'NotFound' || err.$metadata?.httpStatusCode === 404) {
      try {
        await s3Client.send(new CreateBucketCommand({ Bucket: bucket }));
        console.log(`Bucket ${bucket} created successfully.`);
        bucketCreated = true;
      } catch (createErr) {
        console.error("Failed to create bucket:", createErr);
      }
    }
  }
}

export async function uploadFile(
  buffer: Buffer,
  filename: string,
  contentType: string
): Promise<string> {
  const bucket = process.env.S3_BUCKET || "chevereto";
  await ensureBucketExists(bucket);

  const command = new PutObjectCommand({
    Bucket: bucket,
    Key: filename,
    Body: buffer,
    ContentType: contentType,
  });

  await s3Client.send(command);
  
  // Return the public URL if accessible directly, or just the key
  // For local MinIO, the URL format is: endpoint/bucket/key
  const endpoint = process.env.S3_ENDPOINT || "";
  return `${endpoint}/${bucket}/${filename}`;
}
