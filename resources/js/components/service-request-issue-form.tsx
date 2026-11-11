import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';

type IssueKind = 'cancel' | 'dispute';

type Props = {
    endpoint: string;
    status: string;
    disputeStatus: string | null;
    disputeReason: string | null;
    disputeResolution: string | null;
};

export default function ServiceRequestIssueForm({
    endpoint,
    status,
    disputeStatus,
    disputeReason,
    disputeResolution,
}: Props) {
    const [kind, setKind] = useState<IssueKind | null>(null);
    const form = useForm({ reason: '' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (kind === null) return;

        form.post(`${endpoint}/${kind}`, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setKind(null);
            },
        });
    }

    if (
        ![
            'pending',
            'accepted',
            'in_progress',
            'awaiting_confirmation',
        ].includes(status)
    ) {
        return null;
    }

    return (
        <div className="mt-4 border-t pt-4">
            {disputeStatus === 'open' ? (
                <div
                    className="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950"
                    role="status"
                >
                    <p className="font-semibold">
                        Signalement en cours · décision financière bloquée
                    </p>
                    {disputeReason && <p className="mt-1">{disputeReason}</p>}
                </div>
            ) : disputeStatus === 'resolved' ? (
                <div
                    className="rounded-lg border bg-muted p-3 text-sm"
                    role="status"
                >
                    <p className="font-semibold">Signalement traité</p>
                    {disputeResolution && (
                        <p className="mt-1">{disputeResolution}</p>
                    )}
                </div>
            ) : kind === null ? (
                <div className="flex flex-wrap gap-2">
                    {status === 'pending' && (
                        <button
                            type="button"
                            onClick={() => setKind('cancel')}
                            className="min-h-11 rounded-lg border px-3 py-2 text-sm font-medium"
                        >
                            Annuler la demande
                        </button>
                    )}
                    {status !== 'pending' && (
                        <>
                            <button
                                type="button"
                                onClick={() => setKind('dispute')}
                                className="min-h-11 rounded-lg border px-3 py-2 text-sm font-medium"
                            >
                                Signaler un problème
                            </button>
                            <button
                                type="button"
                                onClick={() => setKind('cancel')}
                                className="min-h-11 rounded-lg border px-3 py-2 text-sm font-medium"
                            >
                                Demander une annulation
                            </button>
                        </>
                    )}
                </div>
            ) : (
                <form onSubmit={submit} className="space-y-3">
                    <label
                        htmlFor={`request-issue-${endpoint}`}
                        className="block text-sm font-medium"
                    >
                        {kind === 'cancel'
                            ? 'Motif de la demande d’annulation'
                            : 'Décris le problème'}
                    </label>
                    <textarea
                        id={`request-issue-${endpoint}`}
                        required
                        minLength={10}
                        maxLength={2000}
                        value={form.data.reason}
                        onChange={(event) =>
                            form.setData('reason', event.target.value)
                        }
                        rows={3}
                        className="w-full rounded-lg border bg-background p-3 text-sm"
                    />
                    {form.errors.reason && (
                        <p role="alert" className="text-sm text-destructive">
                            {form.errors.reason}
                        </p>
                    )}
                    <p className="text-xs text-muted-foreground">
                        Aucun frais ni remboursement n’est prélevé ou déclenché
                        ici. Les annulations après acceptation et les litiges
                        sont examinés par l’administration.
                    </p>
                    <div className="flex gap-2">
                        <button
                            type="button"
                            onClick={() => setKind(null)}
                            disabled={form.processing}
                            className="min-h-11 rounded-lg border px-4 py-2 text-sm"
                        >
                            Retour
                        </button>
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="min-h-11 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground disabled:opacity-50"
                        >
                            {form.processing ? 'Envoi…' : 'Envoyer'}
                        </button>
                    </div>
                </form>
            )}
        </div>
    );
}
