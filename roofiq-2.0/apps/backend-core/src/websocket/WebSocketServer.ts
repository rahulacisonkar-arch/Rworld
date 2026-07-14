import { Server as HttpServer } from 'http';
import { WebSocketServer, WebSocket } from 'ws';

export class RoofIQWebSocketServer {
  private wss: WebSocketServer | null = null;
  private clients: Map<string, WebSocket> = new Map();

  initialize(server: HttpServer) {
    this.wss = new WebSocketServer({ noServer: true });

    server.on('upgrade', (request, socket, head) => {
      this.wss?.handleUpgrade(request, socket, head, (ws) => {
        this.wss?.emit('connection', ws, request);
      });
    });

    this.wss.on('connection', (ws: WebSocket) => {
      let clientId = Math.random().toString(36).substring(7);
      this.clients.set(clientId, ws);

      ws.on('close', () => {
        this.clients.delete(clientId);
      });
    });
  }

  broadcast(message: any) {
    const data = JSON.stringify(message);
    this.clients.forEach((ws) => {
      if (ws.readyState === WebSocket.OPEN) {
        ws.send(data);
      }
    });
  }
}

export const wsServer = new RoofIQWebSocketServer();
