import { eventDispatcher } from '../../../apps/backend-core/src/events/EventDispatcher';

describe('Workflow Integration Test', () => {
  it('should propagate triggers sequentially through events bus pipeline', (done) => {
    let triggeredCount = 0;

    eventDispatcher.once('REPORT_GENERATION_QUEUED', (data) => {
      expect(data.propertyId).toBe('prop-123');
      expect(triggeredCount).toBe(5); // Ensure all preceding segments fired
      done();
    });

    eventDispatcher.on('WEATHER_TRIGGERED', () => triggeredCount++);
    eventDispatcher.on('SOLAR_TRIGGERED', () => triggeredCount++);
    eventDispatcher.on('OCR_TRIGGERED', () => triggeredCount++);
    eventDispatcher.on('PERMIT_TRIGGERED', () => triggeredCount++);
    eventDispatcher.on('AI_TRIGGERED', () => triggeredCount++);

    // 1. Kick off the vertical slice flow trigger
    eventDispatcher.emit('ROOF_ANALYSIS_COMPLETE', {
      propertyId: 'prop-123',
      latitude: 40.7128,
      longitude: -74.0060
    });
  });
});
