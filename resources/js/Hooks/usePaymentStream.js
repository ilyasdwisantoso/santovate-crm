import { useEffect, useRef, useState } from 'react';
import { paymentIsComplete, paymentIsTerminal } from '../Utils/paymentStatusPolicy';

export default function usePaymentStream({ reference, streamUrl, statusUrl, initialStatus = 'pending', onComplete }) {
    const [payment, setPayment] = useState({ status: initialStatus, active: false, reference });
    const [connection, setConnection] = useState(reference ? 'connecting' : 'idle');
    const [lastEventAt, setLastEventAt] = useState(null);
    const completedRef = useRef(false);

    useEffect(() => {
        completedRef.current = false;
        setPayment({ status: initialStatus, active: false, reference });
        setConnection(reference ? 'connecting' : 'idle');
    }, [reference, initialStatus]);

    useEffect(() => {
        // PHP artisan serve may serialize long SSE requests, blocking QR image requests locally.
        // Use short JSON polling on loopback; preserve SSE on real web servers.
        const localPolling = typeof window !== 'undefined' && ['localhost', '127.0.0.1'].includes(window.location.hostname);
        if (!reference || !streamUrl || typeof window === 'undefined' || !('EventSource' in window) || localPolling) {
            if (reference) setConnection('fallback');
            return undefined;
        }

        const source = new EventSource(streamUrl, { withCredentials: true });
        const apply = (event, terminal = false) => {
            try {
                const data = JSON.parse(event.data || '{}');
                setPayment(data);
                setLastEventAt(new Date());
                if (paymentIsComplete(data) && !completedRef.current) {
                    completedRef.current = true;
                    setConnection('complete');
                    onComplete?.(data);
                    source.close();
                } else if (terminal || paymentIsTerminal(data)) {
                    setConnection('terminal');
                    source.close();
                }
            } catch {
                // Ignore malformed heartbeat/data; polling fallback remains available.
            }
        };

        source.onopen = () => setConnection('live');
        source.addEventListener('payment', (event) => apply(event));
        source.addEventListener('complete', (event) => apply(event));
        source.addEventListener('terminal', (event) => apply(event, true));
        source.addEventListener('heartbeat', () => setLastEventAt(new Date()));
        source.onerror = () => {
            if (!completedRef.current) setConnection('reconnecting');
        };

        return () => source.close();
    }, [reference, streamUrl, onComplete]);

    useEffect(() => {
        if (!reference || !statusUrl || !['fallback', 'reconnecting'].includes(connection)) return undefined;

        let cancelled = false;
        const check = async () => {
            try {
                const response = await fetch(statusUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!response.ok || cancelled) return;
                const data = await response.json();
                setPayment(data);
                setLastEventAt(new Date());
                if (paymentIsComplete(data) && !completedRef.current) {
                    completedRef.current = true;
                    setConnection('complete');
                    onComplete?.(data);
                } else if (paymentIsTerminal(data)) {
                    setConnection('terminal');
                }
            } catch {
                // Keep the UI usable while EventSource reconnects.
            }
        };

        check();
        const id = window.setInterval(check, 4000);
        return () => {
            cancelled = true;
            window.clearInterval(id);
        };
    }, [reference, statusUrl, connection, onComplete]);

    return { payment, connection, lastEventAt };
}
