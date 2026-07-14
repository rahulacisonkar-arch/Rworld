// Interchangeable Plug-and-Play Plugin Interface
export interface PluginMetadata {
  id: string;
  name: string;
  version: string;
  description: string;
  author: string;
}

export interface RoofIQPlugin {
  metadata: PluginMetadata;

  // Lifecycle Hooks
  initialize(): Promise<void>;
  shutdown(): Promise<void>;
  
  // Diagnostics
  health(): Promise<{ status: 'healthy' | 'unhealthy'; details?: string }>;

  // Execution
  execute(input: any): Promise<any>;
}
