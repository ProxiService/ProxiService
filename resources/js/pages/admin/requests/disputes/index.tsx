import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

type Dispute = {
    id: number;
    status: string;
    reason: string;
    disputed_at: string | null;
    client: string;
    provider: string;
    service: string;
};

type Props = {
    disputes: { data: Dispute[]; total: number };
};

function DisputeCard({ dispute }: { dispute: Dispute }) {
    const form = useForm({
        decision: 'resume' as 'resume' | 'cancel',
        resolution: '',
    });

    function resolve(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(`/admin/requests/${dispute.id}/dispute/resolve`, {
            preserveScroll: true,
        });
    }

    return (
        <article className="rounded-xl border bg-card p-5">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <h2 className="text-lg font-semibold">
                    Demande #{dispute.id} · {dispute.service}
                </h2>
                <span className="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-900">
                    Signalement ouvert
                </span>
            </div>
            <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt className="text-muted-foreground">Client</dt>
                    <dd className="font-medium">{dispute.client}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Prestataire</dt>
                    <dd className="font-medium">{dispute.provider}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">
                        État de la demande
                    </dt>
                    <dd className="font-medium">{dispute.status}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Signalé le</dt>
                    <dd className="font-medium">
                        {dispute.disputed_at ?? '—'}
                    </dd>
                </div>
            </dl>
            <p className="mt-4 rounded-lg bg-muted p-3 text-sm whitespace-pre-wrap">
                {dispute.reason}
            </p>
            <form onSubmit={resolve} className="mt-5 space-y-3">
                <label
                    htmlFor={`resolution-${dispute.id}`}
                    className="block text-sm font-medium"
                >
                    Motif de la décision
                </label>
                <textarea
                    id={`resolution-${dispute.id}`}
                    required
                    minLength={10}
                    maxLength={2000}
                    rows={3}
                    value={form.data.resolution}
                    onChange={(event) =>
                        form.setData('resolution', event.target.value)
                    }
                    className="w-full rounded-lg border bg-background p-3 text-base"
                />
                {form.errors.resolution && (
                    <p role="alert" className="text-sm text-destructive">
                        {form.errors.resolution}
                    </p>
                )}
                <p className="text-xs text-muted-foreground">
                    Cette décision clôt ou reprend le traitement métier. Aucun
                    frais, remboursement ou reversement n’est déclenché.
                </p>
                <div className="flex flex-col gap-2 sm:flex-row">
                    <button
                        type="button"
                        disabled={form.processing}
                        onClick={() => form.setData('decision', 'resume')}
                        aria-pressed={form.data.decision === 'resume'}
                        className="min-h-11 rounded-lg border px-4 py-2 text-sm aria-pressed:bg-muted"
                    >
                        Reprendre le traitement
                    </button>
                    <button
                        type="button"
                        disabled={form.processing}
                        onClick={() => form.setData('decision', 'cancel')}
                        aria-pressed={form.data.decision === 'cancel'}
                        className="min-h-11 rounded-lg border border-red-200 px-4 py-2 text-sm text-red-700 aria-pressed:bg-red-50"
                    >
                        Annuler la demande
                    </button>
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="min-h-11 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50"
                    >
                        {form.processing
                            ? 'Enregistrement…'
                            : 'Enregistrer la décision'}
                    </button>
                </div>
            </form>
        </article>
    );
}

export default function ServiceRequestDisputes({ disputes }: Props) {
    return (
        <>
            <Head title="Litiges de prestations" />
            <main className="flex flex-col gap-6 p-4 md:p-8">
                <header>
                    <p className="text-sm font-semibold tracking-wide text-primary uppercase">
                        ProxiServices · Administration
                    </p>
                    <h1 className="mt-2 text-3xl font-bold">
                        Litiges de prestations
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        {disputes.total} signalement(s) attendent une décision
                        métier.
                    </p>
                </header>
                {disputes.data.length === 0 ? (
                    <p className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        Aucun litige en attente.
                    </p>
                ) : (
                    <div className="grid gap-4">
                        {disputes.data.map((dispute) => (
                            <DisputeCard key={dispute.id} dispute={dispute} />
                        ))}
                    </div>
                )}
            </main>
        </>
    );
}
