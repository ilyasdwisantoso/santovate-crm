import { usePage } from '@inertiajs/react';
import Icon from './Icon';

export default function Flash() {
    const { flash = {}, errors = {} } = usePage().props;
    const firstError = Object.values(errors || {})[0];
    if (!flash.success && !flash.error && !firstError) return null;
    const success = flash.success && !flash.error && !firstError;
    return <div className={`flash ${success ? 'flash-success' : 'flash-error'}`}><Icon name={success ? 'check' : 'alert'} size={18}/><span>{flash.success || flash.error || firstError}</span></div>;
}
