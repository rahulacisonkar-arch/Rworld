import express from 'express';
import { createServer } from 'http';
import cors from 'cors';
import helmet from 'helmet';
import { authMiddleware, errorHandler } from './middleware';
import { propertyController } from './controllers/PropertyController';
import { analysisController } from './controllers/AnalysisController';
import router from './routes';
import { wsServer } from './config/websocket';
import { scheduler } from './jobs/Scheduler';
import './workers'; // Initializes background queue workers

const app = express();
const port = process.env.PORT || 3000;

app.use(helmet());
app.use(cors());
app.use(express.json());

// Apply Auth Middleware to all routes
app.use(authMiddleware);

// Mount core Router (maps /api/v1/property, /api/v1/analysis, /api/v1/report)
app.use('/api/v1', router);

// Global Error Handler
app.use(errorHandler);

const httpServer = createServer(app);

// Initialize WebSocket progress server
wsServer.initialize(httpServer);

// Start recurring scheduler
scheduler.initialize();

httpServer.listen(port, () => {
  console.log(`[RoofIQ Core Engine] Service running on port ${port}`);
});
