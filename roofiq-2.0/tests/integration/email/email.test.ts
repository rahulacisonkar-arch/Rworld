import { queueEmail } from '../../../apps/email-service/src/queues/email.queue';

describe('Email Service Integration Test', () => {
  it('should queue transactional email jobs into BullMQ', async () => {
    // Simulates queueing an email
    await expect(queueEmail(
      'customer@example.com',
      'welcome',
      { name: 'John Doe', verificationUrl: 'http://localhost/verify' }
    )).resolves.not.toThrow();
  });
});
