export class NotificationService {
  async sendCrewAlert(crewId: string, message: string): Promise<boolean> {
    console.log(`[Notification] Dispatching SMS/Push to Crew ${crewId}: ${message}`);
    return true;
  }

  async sendEmailProposal(email: string, proposalUrl: string): Promise<boolean> {
    console.log(`[Notification] Sending proposal proposal PDF link to Client ${email}`);
    return true;
  }
}

export const notificationService = new NotificationService();
