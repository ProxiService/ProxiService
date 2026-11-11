import { useEffect, useState } from 'react';

export default function NetworkStatus() {
    const [online, setOnline] = useState(true);
    useEffect(() => {
        const update = () => setOnline(navigator.onLine);
        update();
        window.addEventListener('online', update);
        window.addEventListener('offline', update);
        return () => {
            window.removeEventListener('online', update);
            window.removeEventListener('offline', update);
        };
    }, []);
    return online ? null : (
        <div
            role="status"
            aria-live="polite"
            className="fixed inset-x-0 top-0 z-[100] bg-amber-100 px-4 py-2 text-center text-sm font-semibold text-amber-950"
        >
            Hors connexion : vos actions ne sont pas envoyées. Rétablissez le
            réseau pour continuer.
        </div>
    );
}
