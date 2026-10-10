/** A different transaction's active subscription is not proof this payment succeeded. */
export const paymentIsComplete = (payload) => payload?.status === 'paid' && payload?.active === true;

const terminalStatuses = new Set(['failed', 'expired', 'cancelled', 'canceled', 'pending_verification']);
export const paymentIsTerminal = (payload) => terminalStatuses.has(payload?.status);
