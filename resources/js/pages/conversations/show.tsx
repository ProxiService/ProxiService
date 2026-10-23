import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarClock,
    CheckCircle2,
    MapPin,
    MessageCircle,
    Send,
    UserRound,
    Wrench,
} from 'lucide-react';
import { FormEvent } from 'react';

type Conversation = {
    id: number;
    description: string;
    city: string;
    neighborhood: string;
    address: string | null;
    scheduled_at: string | null;
    service_name: string;
    client_name: string;
    provider_name: string;
};

type MessageItem = {
    id: number;
    body: string;
    created_at: string | null;
    sender: {
        id: number;
        name: string;
    };
};

type ConversationPageProps = {
    conversation: Conversation;
    messages: MessageItem[];
    currentUserId: number;
    storeUrl: string;
    backUrl: string;
};

export default function ConversationShow({
    conversation,
    messages,
    currentUserId,
    storeUrl,
    backUrl,
}: ConversationPageProps) {
    const form = useForm({
        body: '',
    });

    function submitMessage(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (!form.data.body.trim() || form.processing) {
            return;
        }

        form.transform((data) => ({
            body: data.body.trim(),
        }));

        form.post(storeUrl, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('body');
            },
        });
    }

    return (
        <>
            <Head title={`Conversation — ${conversation.service_name}`} />

            <div className="min-h-screen bg-gray-50 px-4 py-6 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-4xl">
                    {/* Navigation */}
                    <div className="mb-5">
                        <Link
                            href={backUrl}
                            className="inline-flex items-center gap-2 text-sm font-medium text-indigo-600 transition hover:text-indigo-800"
                        >
                            <ArrowLeft className="size-4" />
                            Retour à mes demandes
                        </Link>
                    </div>

                    {/* En-tête de la conversation */}
                    <section className="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                        <div className="flex items-start gap-4 border-b border-gray-100 px-5 py-5 sm:px-6">
                            <div className="flex size-12 shrink-0 items-center justify-center rounded-xl bg-indigo-50">
                                <MessageCircle className="size-6 text-indigo-600" />
                            </div>

                            <div className="min-w-0 flex-1">
                                <h1 className="text-xl font-bold text-gray-900">
                                    {conversation.service_name}
                                </h1>

                                <p className="mt-1 text-sm text-gray-500">
                                    Conversation liée à la demande n°
                                    {conversation.id}
                                </p>

                                <div className="mt-3 inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">
                                    <CheckCircle2 className="size-3.5" />
                                    Demande acceptée
                                </div>
                            </div>
                        </div>

                        {/* Résumé de la demande */}
                        <div className="grid gap-4 bg-gray-50 px-5 py-5 sm:grid-cols-2 sm:px-6">
                            <div className="flex items-start gap-3">
                                <UserRound className="mt-0.5 size-5 shrink-0 text-gray-400" />

                                <div>
                                    <p className="text-xs font-medium uppercase tracking-wide text-gray-500">
                                        Client
                                    </p>

                                    <p className="mt-1 text-sm font-semibold text-gray-900">
                                        {conversation.client_name}
                                    </p>
                                </div>
                            </div>

                            <div className="flex items-start gap-3">
                                <Wrench className="mt-0.5 size-5 shrink-0 text-gray-400" />

                                <div>
                                    <p className="text-xs font-medium uppercase tracking-wide text-gray-500">
                                        Prestataire
                                    </p>

                                    <p className="mt-1 text-sm font-semibold text-gray-900">
                                        {conversation.provider_name}
                                    </p>
                                </div>
                            </div>

                            <div className="flex items-start gap-3">
                                <MapPin className="mt-0.5 size-5 shrink-0 text-gray-400" />

                                <div>
                                    <p className="text-xs font-medium uppercase tracking-wide text-gray-500">
                                        Lieu d’intervention
                                    </p>

                                    <p className="mt-1 text-sm font-semibold text-gray-900">
                                        {conversation.neighborhood},{' '}
                                        {conversation.city}
                                    </p>

                                    {conversation.address && (
                                        <p className="mt-1 text-sm text-gray-500">
                                            {conversation.address}
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div className="flex items-start gap-3">
                                <CalendarClock className="mt-0.5 size-5 shrink-0 text-gray-400" />

                                <div>
                                    <p className="text-xs font-medium uppercase tracking-wide text-gray-500">
                                        Date prévue
                                    </p>

                                    <p className="mt-1 text-sm font-semibold text-gray-900">
                                        {conversation.scheduled_at ??
                                            'À convenir entre les participants'}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    {/* Zone de discussion */}
                    <section className="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                        <div className="border-b border-gray-100 px-5 py-4 sm:px-6">
                            <h2 className="font-semibold text-gray-900">
                                Messages
                            </h2>

                            <p className="mt-1 text-sm text-gray-500">
                                Échange avec l’autre participant au sujet de
                                cette prestation.
                            </p>
                        </div>

                        <div
                            aria-live="polite"
                            className="max-h-[560px] min-h-64 space-y-5 overflow-y-auto bg-gray-50 px-4 py-6 sm:px-6"
                        >
                            {messages.length === 0 ? (
                                <div className="flex min-h-52 flex-col items-center justify-center text-center">
                                    <div className="flex size-12 items-center justify-center rounded-full bg-white shadow-sm">
                                        <MessageCircle className="size-6 text-gray-400" />
                                    </div>

                                    <h3 className="mt-4 text-sm font-semibold text-gray-900">
                                        Aucun message pour le moment
                                    </h3>

                                    <p className="mt-1 max-w-sm text-sm leading-6 text-gray-500">
                                        Envoie le premier message pour
                                        commencer à organiser la prestation.
                                    </p>
                                </div>
                            ) : (
                                messages.map((message) => {
                                    const isMine =
                                        message.sender.id === currentUserId;

                                    return (
                                        <div
                                            key={message.id}
                                            className={`flex ${
                                                isMine
                                                    ? 'justify-end'
                                                    : 'justify-start'
                                            }`}
                                        >
                                            <div
                                                className={`max-w-[88%] sm:max-w-[75%] ${
                                                    isMine
                                                        ? 'items-end'
                                                        : 'items-start'
                                                } flex flex-col`}
                                            >
                                                <p className="mb-1 px-1 text-xs font-medium text-gray-500">
                                                    {isMine
                                                        ? 'Vous'
                                                        : message.sender.name}
                                                </p>

                                                <div
                                                    className={`rounded-2xl px-4 py-3 shadow-sm ${
                                                        isMine
                                                            ? 'rounded-br-md bg-indigo-600 text-white'
                                                            : 'rounded-bl-md border border-gray-200 bg-white text-gray-800'
                                                    }`}
                                                >
                                                    <p className="whitespace-pre-wrap break-words text-sm leading-6">
                                                        {message.body}
                                                    </p>
                                                </div>

                                                {message.created_at && (
                                                    <p className="mt-1 px-1 text-xs text-gray-400">
                                                        {message.created_at}
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    );
                                })
                            )}
                        </div>

                        {/* Formulaire d’envoi */}
                        <div className="border-t border-gray-100 bg-white px-4 py-4 sm:px-6">
                            <form
                                onSubmit={submitMessage}
                                className="space-y-3"
                            >
                                <label
                                    htmlFor="message-body"
                                    className="block text-sm font-semibold text-gray-800"
                                >
                                    Ton message
                                </label>

                                <textarea
                                    id="message-body"
                                    value={form.data.body}
                                    onChange={(event) =>
                                        form.setData(
                                            'body',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Écris ton message ici..."
                                    rows={4}
                                    maxLength={5000}
                                    required
                                    disabled={form.processing}
                                    aria-describedby="message-help"
                                    className="w-full resize-y rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 outline-none transition placeholder:text-gray-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 disabled:cursor-not-allowed disabled:bg-gray-100"
                                />

                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <p
                                        id="message-help"
                                        className="text-xs text-gray-500"
                                    >
                                        {form.data.body.length}/5000 caractères
                                    </p>

                                    <button
                                        type="submit"
                                        disabled={
                                            form.processing ||
                                            !form.data.body.trim()
                                        }
                                        className="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        <Send className="size-4" />

                                        {form.processing
                                            ? 'Envoi en cours...'
                                            : 'Envoyer le message'}
                                    </button>
                                </div>

                                {form.errors.body && (
                                    <p
                                        role="alert"
                                        className="text-sm font-medium text-red-600"
                                    >
                                        {form.errors.body}
                                    </p>
                                )}
                            </form>
                        </div>
                    </section>

                    <p className="mx-auto mt-5 max-w-2xl text-center text-xs leading-5 text-gray-500">
                        Les messages sont associés à cette demande et ne sont
                        accessibles qu’aux participants autorisés.
                    </p>
                </div>
            </div>
        </>
    );
}
