<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import type { Book, BookStats } from '@/types';
import { Toaster } from '@/components/ui/sonner';
import { toast } from 'vue-sonner';
import { useAppearance } from '@/composables/useAppearance';
import {
    BookOpen,
    Check,
    Search,
    X,
    Plus,
    Moon,
    Sun,
    Sparkles,
    ArrowUpDown,
    ExternalLink,
    Maximize2
} from '@lucide/vue';

interface Props {
    books: Book[];
    stats: BookStats;
}

const props = defineProps<Props>();

// Dark / Light mode toggle
const { resolvedAppearance, updateAppearance } = useAppearance();
const toggleTheme = () => {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
};

// Local reactive copies for instant optimistic updates
const localBooks = ref<Book[]>([...props.books]);
const localStats = ref<BookStats>({ ...props.stats });

// Search & Filter state
const searchQuery = ref('');
const activeFilter = ref<'all' | 'purchased' | 'unpurchased' | 'with_image'>('all');
const sortBy = ref<'id' | 'title' | 'author'>('id');

// Track expanded descriptions
const expandedDescriptions = ref<Record<number, boolean>>({});
const toggleDescription = (id: number) => {
    expandedDescriptions.value[id] = !expandedDescriptions.value[id];
};

// Lightbox preview for cover
const previewCover = ref<Book | null>(null);

// Filtering and sorting logic
const filteredBooks = computed(() => {
    let result = localBooks.value;

    // Filter by purchase status or image presence
    if (activeFilter.value === 'purchased') {
        result = result.filter((b) => b.is_purchased);
    } else if (activeFilter.value === 'unpurchased') {
        result = result.filter((b) => !b.is_purchased);
    } else if (activeFilter.value === 'with_image') {
        result = result.filter((b) => Boolean(b.image_path || b.image_url));
    }

    // Filter by search query (title, author, details, description)
    const q = searchQuery.value.trim().toLowerCase();
    if (q) {
        result = result.filter((b) =>
            b.title.toLowerCase().includes(q) ||
            b.author.toLowerCase().includes(q) ||
            (b.details && b.details.toLowerCase().includes(q)) ||
            (b.description && b.description.toLowerCase().includes(q))
        );
    }

    // Sort
    return [...result].sort((a, b) => {
        if (sortBy.value === 'title') {
            return a.title.localeCompare(b.title, 'pl');
        }
        if (sortBy.value === 'author') {
            return a.author.localeCompare(b.author, 'pl');
        }
        return a.id - b.id;
    });
});

// Calculate progress percentage
const progressPercentage = computed(() => {
    if (localStats.value.total === 0) return 0;
    return Math.round((localStats.value.purchased / localStats.value.total) * 100);
});

// Toggle purchase status with optimistic update
const isSaving = ref<Record<number, boolean>>({});

const toggleBook = (book: Book) => {
    const previousState = book.is_purchased;
    const newState = !previousState;
    book.is_purchased = newState;

    if (newState) {
        localStats.value.purchased++;
        localStats.value.unpurchased--;
        toast.success(`Kupiono: „${book.title}”`, {
            description: book.author,
            duration: 2500,
        });
    } else {
        localStats.value.purchased--;
        localStats.value.unpurchased++;
        toast.info(`Oznaczono do kupienia: „${book.title}”`, {
            description: book.author,
            duration: 2500,
        });
    }

    isSaving.value[book.id] = true;

    router.patch(
        `/books/${book.id}/toggle`,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                isSaving.value[book.id] = false;
            },
            onError: () => {
                // Rollback on error
                book.is_purchased = previousState;
                if (previousState) {
                    localStats.value.purchased++;
                    localStats.value.unpurchased--;
                } else {
                    localStats.value.purchased--;
                    localStats.value.unpurchased++;
                }
                toast.error('Nie udało się zapisać zmiany.');
            },
        }
    );
};

// Modal for adding a new book
const isAddModalOpen = ref(false);
const form = useForm({
    title: '',
    author: '',
    details: '',
    description: '',
    book_url: '',
    is_purchased: false,
});

const submitAddBook = () => {
    form.post('/books', {
        preserveScroll: true,
        onSuccess: () => {
            isAddModalOpen.value = false;
            form.reset();
            toast.success('Dodano nową książkę do bazy!');
            localBooks.value = [...props.books];
            localStats.value = { ...props.stats };
        },
    });
};
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900 transition-colors duration-200 dark:bg-slate-950 dark:text-slate-100">
        <Head title="Moja Kolekcja Żółtego Tygrysa" />
        <Toaster position="top-right" richColors />

        <!-- Header Bar -->
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/90 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/90">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3 sm:px-6">
                <div class="flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-xl bg-amber-500 font-bold text-slate-950 shadow-md shadow-amber-500/20">
                        <BookOpen class="size-5" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h1 class="text-lg font-bold tracking-tight text-slate-900 sm:text-xl dark:text-white">
                                Żółty Tygrys
                            </h1>
                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-950/70 dark:text-amber-400">
                                Kolekcja Bellona
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Rejestr zakupionych tomów z opisami i okładkami
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 sm:gap-3">
                    <button
                        type="button"
                        @click="isAddModalOpen = true"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-semibold text-slate-950 shadow-sm transition hover:bg-amber-400 active:scale-95 sm:text-sm"
                    >
                        <Plus class="size-4" />
                        <span>Dodaj książkę</span>
                    </button>

                    <!-- Theme Toggle -->
                    <button
                        type="button"
                        @click="toggleTheme"
                        class="flex size-9 items-center justify-center rounded-lg border border-slate-200 bg-slate-100 text-slate-600 transition hover:bg-slate-200 dark:border-slate-800 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                        :title="resolvedAppearance === 'dark' ? 'Przełącz na tryb jasny' : 'Przełącz na tryb ciemny'"
                    >
                        <Sun v-if="resolvedAppearance === 'dark'" class="size-4 text-amber-400" />
                        <Moon v-else class="size-4" />
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-8">
            <!-- Stats & Progress Hero Card -->
            <div class="mb-8 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="p-5 sm:p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <span class="text-xs font-semibold tracking-wider text-amber-600 uppercase dark:text-amber-400">
                                Stan Twojej Kolekcji
                            </span>
                            <h2 class="mt-1 text-2xl font-black text-slate-900 sm:text-3xl dark:text-white">
                                {{ localStats.purchased }} z {{ localStats.total }} tomów kupionych
                            </h2>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                Kliknij checkbox lub kartę książki, aby zapisać zakup. Kliknij w okładkę, aby powiększyć.
                            </p>
                        </div>
                        <div class="flex items-center gap-2 self-start rounded-xl bg-slate-100 p-3 sm:self-auto dark:bg-slate-800">
                            <Sparkles class="size-6 text-amber-500" />
                            <div>
                                <div class="text-2xl font-black text-slate-900 dark:text-white">
                                    {{ progressPercentage }}%
                                </div>
                                <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">
                                    skompletowano
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Progress Bar -->
                    <div class="mt-5">
                        <div class="h-3 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                            <div
                                class="h-full rounded-full bg-gradient-to-r from-amber-500 to-amber-400 transition-all duration-500 ease-out"
                                :style="{ width: `${progressPercentage}%` }"
                            />
                        </div>
                    </div>

                    <!-- Stats Badges -->
                    <div class="mt-5 grid grid-cols-3 gap-3 border-t border-slate-100 pt-5 text-center dark:border-slate-800">
                        <div class="rounded-lg bg-slate-50 p-2.5 dark:bg-slate-800/50">
                            <div class="text-xs font-medium text-slate-500 dark:text-slate-400">Wszystkie</div>
                            <div class="mt-0.5 text-lg font-bold text-slate-900 dark:text-white">{{ localStats.total }}</div>
                        </div>
                        <div class="rounded-lg bg-emerald-50 p-2.5 dark:bg-emerald-950/30">
                            <div class="text-xs font-medium text-emerald-700 dark:text-emerald-400">Kupione</div>
                            <div class="mt-0.5 text-lg font-bold text-emerald-800 dark:text-emerald-300">{{ localStats.purchased }}</div>
                        </div>
                        <div class="rounded-lg bg-amber-50 p-2.5 dark:bg-amber-950/30">
                            <div class="text-xs font-medium text-amber-700 dark:text-amber-400">Do kupienia</div>
                            <div class="mt-0.5 text-lg font-bold text-amber-800 dark:text-amber-300">{{ localStats.unpurchased }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Toolbar: Search, Filters, Sort -->
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <!-- Search bar -->
                <div class="relative flex-1">
                    <Search class="absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Szukaj po tytule, autorze, opisie..."
                        class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pr-10 pl-10 text-sm text-slate-900 shadow-xs transition placeholder:text-slate-400 focus:border-amber-500 focus:ring-2 focus:ring-amber-500/20 focus:outline-none dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100 dark:placeholder:text-slate-500 dark:focus:border-amber-400"
                    />
                    <button
                        v-if="searchQuery"
                        type="button"
                        @click="searchQuery = ''"
                        class="absolute top-1/2 right-3 -translate-y-1/2 rounded-full p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <!-- Filters & Sort -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Filter tabs -->
                    <div class="flex rounded-xl border border-slate-200 bg-white p-1 text-xs font-medium shadow-xs dark:border-slate-800 dark:bg-slate-900">
                        <button
                            type="button"
                            @click="activeFilter = 'all'"
                            class="rounded-lg px-3 py-1.5 transition"
                            :class="activeFilter === 'all'
                                ? 'bg-amber-500 font-bold text-slate-950 shadow-xs'
                                : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
                        >
                            Wszystkie ({{ localStats.total }})
                        </button>
                        <button
                            type="button"
                            @click="activeFilter = 'unpurchased'"
                            class="rounded-lg px-3 py-1.5 transition"
                            :class="activeFilter === 'unpurchased'
                                ? 'bg-amber-500 font-bold text-slate-950 shadow-xs'
                                : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
                        >
                            Do kupienia ({{ localStats.unpurchased }})
                        </button>
                        <button
                            type="button"
                            @click="activeFilter = 'purchased'"
                            class="rounded-lg px-3 py-1.5 transition"
                            :class="activeFilter === 'purchased'
                                ? 'bg-amber-500 font-bold text-slate-950 shadow-xs'
                                : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
                        >
                            Kupione ({{ localStats.purchased }})
                        </button>
                        <button
                            type="button"
                            @click="activeFilter = 'with_image'"
                            class="hidden md:inline-block rounded-lg px-3 py-1.5 transition"
                            :class="activeFilter === 'with_image'
                                ? 'bg-amber-500 font-bold text-slate-950 shadow-xs'
                                : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white'"
                            title="Pokaż tomy z pobraną okładką"
                        >
                            Z okładką
                        </button>
                    </div>

                    <!-- Sort select -->
                    <div class="relative">
                        <select
                            v-model="sortBy"
                            class="appearance-none rounded-xl border border-slate-200 bg-white py-2 pr-8 pl-3 text-xs font-medium text-slate-700 shadow-xs focus:border-amber-500 focus:outline-none dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                        >
                            <option value="id">Kolejność w spisie</option>
                            <option value="title">Tytuł (A-Z)</option>
                            <option value="author">Autor (A-Z)</option>
                        </select>
                        <ArrowUpDown class="pointer-events-none absolute top-1/2 right-2.5 size-3.5 -translate-y-1/2 text-slate-400" />
                    </div>
                </div>
            </div>

            <!-- Empty Search / Filter State -->
            <div
                v-if="filteredBooks.length === 0"
                class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800">
                    <Search class="size-6 text-slate-400" />
                </div>
                <h3 class="mt-4 text-base font-bold text-slate-900 dark:text-white">Nie znaleziono książek</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Brak wyników pasujących do kryteriów wyszukiwania lub wybranego filtra.
                </p>
                <button
                    v-if="searchQuery || activeFilter !== 'all'"
                    type="button"
                    @click="searchQuery = ''; activeFilter = 'all'"
                    class="mt-4 inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                >
                    Zresetuj filtry
                </button>
            </div>

            <!-- Book List -->
            <div v-else class="space-y-3.5">
                <div
                    v-for="book in filteredBooks"
                    :key="book.id"
                    @click="toggleBook(book)"
                    class="group relative flex cursor-pointer items-start gap-4 sm:gap-6 rounded-2xl border p-4 sm:p-5 transition-all duration-150 select-none"
                    :class="book.is_purchased
                        ? 'border-emerald-200 bg-emerald-50/40 hover:bg-emerald-50/70 dark:border-emerald-950/60 dark:bg-emerald-950/20 dark:hover:bg-emerald-950/30'
                        : 'border-slate-200 bg-white hover:border-slate-300 hover:shadow-md dark:border-slate-800/80 dark:bg-slate-900 dark:hover:border-slate-700'"
                >
                    <!-- Checkbox Container -->
                    <div class="flex pt-1.5 sm:pt-2">
                        <button
                            type="button"
                            @click.stop="toggleBook(book)"
                            class="flex size-6 items-center justify-center rounded-md border transition-all duration-150"
                            :class="book.is_purchased
                                ? 'border-emerald-500 bg-emerald-500 text-white shadow-xs dark:border-emerald-500 dark:bg-emerald-500'
                                : 'border-slate-300 bg-white group-hover:border-amber-500 dark:border-slate-700 dark:bg-slate-800'"
                            :title="book.is_purchased ? 'Kliknij, aby odznaczyć' : 'Kliknij, aby oznaczyć jako kupioną'"
                        >
                            <Check v-if="book.is_purchased" class="size-4 stroke-[3]" />
                        </button>
                    </div>

                    <!-- Enriched Book Cover (Enlarged with proper 2:3 aspect ratio) -->
                    <div
                        class="group/cover relative w-20 sm:w-28 md:w-32 aspect-[2/3] shrink-0 overflow-hidden rounded-xl border border-slate-200 bg-slate-100 p-0.5 shadow-sm transition hover:shadow-md dark:border-slate-800 dark:bg-slate-800/80"
                        @click.stop="previewCover = book"
                        :title="(book.image_path || book.image_url) ? 'Kliknij, aby powiększyć okładkę' : ''"
                        :class="{ 'cursor-zoom-in': Boolean(book.image_path || book.image_url) }"
                    >
                        <img
                            v-if="book.image_path || book.image_url"
                            :src="book.image_path || book.image_url || ''"
                            :alt="book.title"
                            loading="lazy"
                            class="size-full rounded-lg object-contain transition duration-200 group-hover/cover:scale-105"
                        />
                        <div v-else class="flex size-full flex-col items-center justify-center p-2 text-center text-amber-700/60 dark:text-amber-400/60">
                            <BookOpen class="size-8" />
                            <span class="mt-1 text-[9px] font-bold tracking-tight uppercase">Tygrys</span>
                        </div>

                        <!-- Hover zoom icon indicator -->
                        <div
                            v-if="book.image_path || book.image_url"
                            class="absolute inset-0 flex items-center justify-center bg-black/30 opacity-0 transition group-hover/cover:opacity-100 rounded-xl"
                        >
                            <Maximize2 class="size-5 text-white drop-shadow" />
                        </div>
                    </div>

                    <!-- Book info and description -->
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-1">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-mono font-medium text-slate-400 dark:text-slate-600">
                                    #{{ book.id }}
                                </span>
                                <h3
                                    class="text-base sm:text-lg font-bold transition"
                                    :class="book.is_purchased
                                        ? 'text-slate-500 line-through dark:text-slate-400'
                                        : 'text-slate-900 group-hover:text-amber-600 dark:text-slate-100 dark:group-hover:text-amber-400'"
                                >
                                    {{ book.title }}
                                </h3>
                            </div>

                            <!-- Status Pill (desktop) -->
                            <div class="shrink-0 hidden sm:block">
                                <span
                                    v-if="book.is_purchased"
                                    class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                                >
                                    <Check class="size-3 stroke-[3]" />
                                    <span>Kupiona</span>
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600 group-hover:bg-amber-100 group-hover:text-amber-800 dark:bg-slate-800 dark:text-slate-400 dark:group-hover:bg-amber-950/60 dark:group-hover:text-amber-400"
                                >
                                    Do kupienia
                                </span>
                            </div>
                        </div>

                        <!-- Author & Release details -->
                        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                            <span class="font-medium text-slate-700 dark:text-slate-300">
                                {{ book.author }}
                            </span>
                            <span v-if="book.details" class="text-slate-400 dark:text-slate-500">
                                • {{ book.details }}
                            </span>
                        </div>

                        <!-- Description (if available) -->
                        <div v-if="book.description" class="mt-2.5">
                            <p
                                class="text-xs sm:text-sm leading-relaxed text-slate-600 transition-all dark:text-slate-300"
                                :class="{ 'line-clamp-3': !expandedDescriptions[book.id] }"
                            >
                                {{ book.description }}
                            </p>
                            <button
                                v-if="book.description.length > 150"
                                type="button"
                                @click.stop="toggleDescription(book.id)"
                                class="mt-1 text-[11px] font-semibold text-amber-600 hover:text-amber-700 dark:text-amber-400 dark:hover:text-amber-300"
                            >
                                {{ expandedDescriptions[book.id] ? 'Zwiń opis ▲' : 'Czytaj więcej ▼' }}
                            </button>
                        </div>

                        <!-- Links & Mobile Status -->
                        <div class="mt-3.5 flex flex-wrap items-center justify-between gap-2 pt-1 border-t border-slate-100 dark:border-slate-800/60">
                            <!-- Bookstore Link -->
                            <a
                                v-if="book.book_url"
                                :href="book.book_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                @click.stop
                                class="inline-flex items-center gap-1.5 rounded-lg border border-amber-300/80 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-900 shadow-2xs transition hover:bg-amber-100 hover:border-amber-400 dark:border-amber-800/70 dark:bg-amber-950/40 dark:text-amber-300 dark:hover:bg-amber-900/60"
                                title="Otwórz stronę książki w księgarni Świat Książki"
                            >
                                <ExternalLink class="size-3.5" />
                                <span>Kup na ŚwiatKsiążki.pl</span>
                            </a>
                            <span v-else class="text-[11px] text-slate-400 dark:text-slate-600 italic">
                                Tom archiwalny Bellona
                            </span>

                            <!-- Status Pill (mobile only) -->
                            <div class="sm:hidden">
                                <span
                                    v-if="book.is_purchased"
                                    class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                                >
                                    <Check class="size-3 stroke-[3]" />
                                    <span>Kupiona</span>
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-400"
                                >
                                    Do kupienia
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer summary -->
            <div class="mt-8 text-center text-xs text-slate-400 dark:text-slate-600">
                Łącznie pozycji na liście: {{ localBooks.length }} • Wszystkie dane zapisują się automatycznie w bazie MySQL
            </div>
        </main>

        <!-- Lightbox Modal for Cover Image -->
        <div
            v-if="previewCover"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm animate-in fade-in"
            @click="previewCover = null"
        >
            <div
                class="relative max-w-sm sm:max-w-md w-full rounded-2xl bg-white p-5 shadow-2xl dark:bg-slate-900 border border-slate-200 dark:border-slate-800"
                @click.stop
            >
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div class="min-w-0 pr-3">
                        <h4 class="text-base font-bold text-slate-900 dark:text-white truncate">
                            {{ previewCover.title }}
                        </h4>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ previewCover.author }}
                        </p>
                    </div>
                    <button
                        type="button"
                        @click="previewCover = null"
                        class="rounded-lg p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 shrink-0"
                    >
                        <X class="size-5" />
                    </button>
                </div>

                <div class="my-4 flex justify-center bg-slate-50 dark:bg-slate-950 p-3 rounded-xl border border-slate-100 dark:border-slate-800">
                    <img
                        :src="previewCover.image_path || previewCover.image_url || ''"
                        :alt="previewCover.title"
                        class="max-h-[65vh] rounded-lg shadow-md object-contain"
                    />
                </div>

                <div class="flex items-center justify-between pt-2">
                    <span class="text-xs text-slate-500 dark:text-slate-400">
                        {{ previewCover.details || 'Seria Żółty Tygrys' }}
                    </span>
                    <a
                        v-if="previewCover.book_url"
                        :href="previewCover.book_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-1 text-xs font-semibold text-amber-600 hover:text-amber-700 dark:text-amber-400 dark:hover:text-amber-300"
                    >
                        <ExternalLink class="size-3.5" />
                        <span>Przejdź do księgarni</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Modal: Dodaj książkę -->
        <div
            v-if="isAddModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-xs"
            @click.self="isAddModalOpen = false"
        >
            <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white">
                        Dodaj książkę do listy
                    </h3>
                    <button
                        type="button"
                        @click="isAddModalOpen = false"
                        class="rounded-lg p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                    >
                        <X class="size-5" />
                    </button>
                </div>

                <form @submit.prevent="submitAddBook" class="mt-4 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Tytuł książki <span class="text-rose-500">*</span>
                        </label>
                        <input
                            v-model="form.title"
                            type="text"
                            required
                            placeholder="np. Cyrk Skalskiego"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-xs focus:border-amber-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        />
                        <div v-if="form.errors.title" class="mt-1 text-xs text-rose-500">
                            {{ form.errors.title }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Autor <span class="text-rose-500">*</span>
                        </label>
                        <input
                            v-model="form.author"
                            type="text"
                            required
                            placeholder="np. Bohdan Arct"
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-xs focus:border-amber-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        />
                        <div v-if="form.errors.author" class="mt-1 text-xs text-rose-500">
                            {{ form.errors.author }}
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Opis książki
                        </label>
                        <textarea
                            v-model="form.description"
                            rows="2"
                            placeholder="Krótki opis lub streszczenie..."
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-xs focus:border-amber-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">
                            Link do księgarni
                        </label>
                        <input
                            v-model="form.book_url"
                            type="url"
                            placeholder="https://www.swiatksiazki.pl/..."
                            class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-xs focus:border-amber-500 focus:outline-none dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        />
                    </div>

                    <div class="flex items-center gap-2 pt-1">
                        <input
                            id="modal_is_purchased"
                            v-model="form.is_purchased"
                            type="checkbox"
                            class="size-4 rounded border-slate-300 text-amber-500 focus:ring-amber-500 dark:border-slate-700 dark:bg-slate-800"
                        />
                        <label for="modal_is_purchased" class="text-xs font-medium text-slate-700 dark:text-slate-300">
                            Książka już została kupiona
                        </label>
                    </div>

                    <div class="mt-6 flex justify-end gap-3 pt-2">
                        <button
                            type="button"
                            @click="isAddModalOpen = false"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                        >
                            Anuluj
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-amber-500 px-4 py-2 text-xs font-semibold text-slate-950 transition hover:bg-amber-400 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Zapisywanie...' : 'Zapisz książkę' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
