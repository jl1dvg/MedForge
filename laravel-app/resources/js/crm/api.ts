import axios from 'axios';
import type { CrmOpportunity, CrmContact, CrmActivity, OpportunitiesResponse, PanelStats } from './types';

const client = axios.create({ baseURL: '/v2/crm', headers: { 'X-Requested-With': 'XMLHttpRequest' } });

export interface OpportunityFilters {
  stage?: string;
  source?: string;
  phase?: string;
  search?: string;
  urgent?: boolean;
  limit?: number;
  offset?: number;
}

export const api = {
  opportunities: {
    list: (filters: OpportunityFilters = {}): Promise<OpportunitiesResponse> =>
      client.get('/opportunities', { params: filters }).then(r => r.data),

    get: (id: number): Promise<CrmOpportunity> =>
      client.get(`/opportunities/${id}`).then(r => r.data.data),

    update: (id: number, payload: Partial<Pick<CrmOpportunity, 'stage' | 'phase' | 'assigned_to' | 'lost_reason'>>): Promise<CrmOpportunity> =>
      client.patch(`/opportunities/${id}`, payload).then(r => r.data.data),

    addActivity: (id: number, type: string, description: string): Promise<CrmActivity> =>
      client.post(`/opportunities/${id}/activities`, { type, description }).then(r => r.data.data),
  },

  contacts: {
    update: (id: number, payload: Partial<CrmContact>): Promise<CrmContact> =>
      client.patch(`/contacts/${id}`, payload).then(r => r.data.data),

    merge: (id: number, mergeIntoId: number): Promise<CrmContact> =>
      client.post(`/contacts/${id}/merge`, { merge_into_id: mergeIntoId }).then(r => r.data.data),
  },

  stats: {
    panel: (): Promise<{ panel: PanelStats; by_stage: Record<string, number>; by_phase: Record<string, number> }> =>
      client.get('/stats').then(r => r.data.data),
  },
};
