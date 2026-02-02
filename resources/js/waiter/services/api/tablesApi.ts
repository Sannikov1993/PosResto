/**
 * Waiter App - Tables API Service
 * Handles zones and tables API calls
 */

import { api } from './client';
import type {
  ApiResponse,
  ZonesResponse,
  TablesResponse,
  TableResponse,
  OpenTableRequest,
  OpenTableResponse,
  Zone,
  Table,
} from '@/waiter/types';

export const tablesApi = {
  // === Zones ===

  /**
   * Get all zones
   */
  async getZones(): Promise<ZonesResponse> {
    return api.get<ZonesResponse>('/zones');
  },

  /**
   * Get zone by ID
   */
  async getZone(zoneId: number): Promise<ApiResponse<Zone>> {
    return api.get<ApiResponse<Zone>>(`/zones/${zoneId}`);
  },

  // === Tables ===

  /**
   * Get all tables
   */
  async getTables(): Promise<TablesResponse> {
    return api.get<TablesResponse>('/tables');
  },

  /**
   * Get tables by zone
   */
  async getTablesByZone(zoneId: number): Promise<TablesResponse> {
    return api.get<TablesResponse>('/tables', { zone_id: zoneId });
  },

  /**
   * Get table by ID with current order
   */
  async getTable(tableId: number): Promise<TableResponse> {
    return api.get<TableResponse>(`/waiter/tables/${tableId}`);
  },

  /**
   * Open table (create new order)
   */
  async openTable(tableId: number, data: OpenTableRequest): Promise<ApiResponse<OpenTableResponse>> {
    return api.post<ApiResponse<OpenTableResponse>>(`/waiter/tables/${tableId}/open`, data);
  },

  /**
   * Close table (after payment)
   */
  async closeTable(tableId: number): Promise<TableResponse> {
    return api.post<TableResponse>(`/waiter/tables/${tableId}/close`);
  },

  /**
   * Request bill for table
   */
  async requestBill(tableId: number): Promise<TableResponse> {
    return api.post<TableResponse>(`/waiter/tables/${tableId}/request-bill`);
  },

  /**
   * Transfer order to another table
   */
  async transferOrder(fromTableId: number, toTableId: number): Promise<TableResponse> {
    return api.post<TableResponse>(`/waiter/tables/${fromTableId}/transfer`, {
      target_table_id: toTableId,
    });
  },

  /**
   * Merge tables (combine orders)
   */
  async mergeTables(mainTableId: number, tableIds: number[]): Promise<TableResponse> {
    return api.post<TableResponse>(`/waiter/tables/${mainTableId}/merge`, {
      table_ids: tableIds,
    });
  },

  // === Batch Operations ===

  /**
   * Get zones and tables in one request
   */
  async getAll(): Promise<{ zones: Zone[]; tables: Table[] }> {
    const [zonesRes, tablesRes] = await Promise.all([
      this.getZones(),
      this.getTables(),
    ]);

    return {
      zones: zonesRes.success ? zonesRes.data : [],
      tables: tablesRes.success ? tablesRes.data : [],
    };
  },
};
