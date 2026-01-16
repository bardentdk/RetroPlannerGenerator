<!-- <script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { 
    PhCloudArrowUp, PhCheckCircle, PhXCircle, PhSpinner, 
    PhFilePdf, PhTrash, PhPencilSimple, PhUsers, PhMagnifyingGlass, PhCaretRight, PhWarning 
} from '@phosphor-icons/vue';
import gsap from 'gsap';

const props = defineProps({
    schedule: Object,
    students: Array,
    selectedStudent: String,
    currentFile: Object, // Peut être null si rien en cours
    errors: Object,
    flash: Object
});

// --- SYSTÈME DE POLLING ROBUSTE ---
const localActiveFile = ref(props.currentFile); // Copie locale pour modification temps réel
const progressPercent = ref(0);
let pollInterval = null;

// Fonction de polling qui tourne en boucle
const startPolling = (fileId) => {
    if (pollInterval) clearInterval(pollInterval);
    
    // On met à jour immédiatement l'état local pour afficher la barre
    if (!localActiveFile.value) {
        localActiveFile.value = { id: fileId, status: 'pending', processed_pages: 0, total_pages: 0 };
    }

    pollInterval = setInterval(async () => {
        try {
            // Appel API léger (ne recharge pas la page)
            const res = await fetch(`/upload/status/${fileId}`);
            if (!res.ok) return; // Erreur silencieuse
            
            const data = await res.json();
            localActiveFile.value = data; // Mise à jour réactive
            
            // Calcul pourcentage
            if (data.total_pages > 0) {
                progressPercent.value = Math.round((data.processed_pages / data.total_pages) * 100);
            }

            // Si fini
            if (data.status === 'completed') {
                clearInterval(pollInterval);
                progressPercent.value = 100;
                
                // On attend 1s pour que l'utilisateur voie "100%"
                setTimeout(() => {
                    // Rechargement COMPLET Inertia pour afficher les nouvelles données
                    router.visit('/', { 
                        only: ['schedule', 'students', 'flash', 'currentFile'],
                        preserveScroll: true 
                    });
                    localActiveFile.value = null; // Masquer la barre
                }, 1000);
            }
            
            // Si erreur
            if (data.status === 'failed') {
                clearInterval(pollInterval);
            }

        } catch (e) {
            console.error("Erreur polling:", e);
        }
    }, 1000); // Check toutes les 1 seconde (plus fluide)
};

// Démarrage auto si on arrive sur la page et qu'un fichier est en cours
onMounted(() => {
    if (props.currentFile && ['pending', 'processing'].includes(props.currentFile.status)) {
        startPolling(props.currentFile.id);
    }
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});

// --- UPLOAD ---
const formUpload = useForm({ file: null });

const handleFile = (e) => {
    const files = e.target.files || e.dataTransfer.files;
    if (files.length) {
        formUpload.file = files[0];
        
        // Animation
        gsap.to('.upload-area', { scale: 0.98, duration: 0.1, yoyo: true, repeat: 1 });

        formUpload.post('/upload', {
            onSuccess: (page) => {
                // Dès que le serveur répond "OK uploadé", on récupère l'ID du fichier créé
                // Le contrôleur a redirigé vers index, donc props.currentFile est à jour
                if (page.props.currentFile) {
                    localActiveFile.value = page.props.currentFile;
                    startPolling(page.props.currentFile.id);
                }
            },
            onError: () => {
                alert("Erreur lors de l'upload. Vérifiez la taille du fichier.");
            }
        });
    }
};

// --- NAVIGATION & FILTRES ---
const searchQuery = ref('');
const filteredStudents = computed(() => {
    if (!props.students) return [];
    if (!searchQuery.value) return props.students;
    return props.students.filter(s => s.toLowerCase().includes(searchQuery.value.toLowerCase()));
});

const selectStudent = (name) => {
    router.get('/', { student: name }, { preserveState: true, preserveScroll: true });
};

// --- CRUD ---
const showEditModal = ref(false);
const formEdit = useForm({ id: null, student_name: '', is_present: false, date: '' });
const openEdit = (slot) => {
    formEdit.id = slot.id; formEdit.student_name = slot.student_name;
    formEdit.is_present = Boolean(slot.is_present); formEdit.date = slot.date.split('T')[0];
    showEditModal.value = true;
};
const submitEdit = () => { formEdit.put(`/slot/${formEdit.id}`, { preserveScroll: true, onSuccess: () => showEditModal.value = false }); };
const deleteSlot = (id) => { if(confirm('Supprimer ?')) router.delete(`/slot/${id}`, { preserveScroll: true }); };

// Helpers
const formatDate = (d) => new Date(d).toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'long' });
const getStatusColor = (p) => p ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : 'bg-rose-100 text-rose-700 border-rose-200';
</script> -->
<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import MainLayout from '@/Layouts/MainLayout.vue';
import { 
    PhCloudArrowUp, PhCheckCircle, PhXCircle, PhSpinner, 
    PhFilePdf, PhTrash, PhPencilSimple, PhUsers, PhMagnifyingGlass, PhCaretRight, PhWarning 
} from '@phosphor-icons/vue';
import gsap from 'gsap';

const props = defineProps({
    schedule: Object,
    students: Array,
    selectedStudent: String,
    currentFile: Object, 
    errors: Object,
    flash: Object
});

// --- SYSTÈME DE POLLING ANTI-CACHE ---
// On crée une copie locale réactive. On ne touche plus aux props après le chargement.
const processingFile = ref(props.currentFile); 
const progressPercent = ref(0);
let pollInterval = null;

const startPolling = (fileId) => {
    // 1. On nettoie tout intervalle existant pour éviter les doublons
    if (pollInterval) clearInterval(pollInterval);
    
    // 2. On initialise l'état local si ce n'est pas déjà fait
    if (!processingFile.value) {
        processingFile.value = { id: fileId, status: 'pending', processed_pages: 0, total_pages: 0 };
    }

    // 3. Boucle infinie toutes les 1s
    pollInterval = setInterval(async () => {
        try {
            // ASTUCE ANTI-CACHE : On ajoute ?t=timestamp pour forcer le navigateur à faire la requête
            const timestamp = new Date().getTime();
            const res = await fetch(`/upload/status/${fileId}?t=${timestamp}`);
            
            if (!res.ok) return;
            
            const data = await res.json();
            
            // Mise à jour des données locales (réactivité Vue)
            processingFile.value = data;
            
            // Calcul du pourcentage
            if (data.total_pages > 0) {
                progressPercent.value = Math.round((data.processed_pages / data.total_pages) * 100);
            } else {
                progressPercent.value = 0; // Sécurité si total_pages = 0
            }

            // Si c'est fini (Succès ou Échec)
            if (data.status === 'completed' || data.status === 'failed') {
                clearInterval(pollInterval);
                
                if (data.status === 'completed') {
                    progressPercent.value = 100;
                    // Petit délai pour voir les 100% avant de rafraîchir les données
                    setTimeout(() => {
                        // On recharge Inertia pour récupérer le planning et nettoyer la vue
                        router.visit(window.location.pathname + window.location.search, {
                            only: ['schedule', 'students', 'flash', 'currentFile'],
                            preserveScroll: true,
                            onFinish: () => {
                                processingFile.value = null; // On cache la barre
                            }
                        });
                    }, 1000);
                }
            }
        } catch (e) {
            console.error("Erreur polling:", e);
            // On ne coupe pas l'intervalle sur une erreur réseau ponctuelle, on réessaie au prochain tick
        }
    }, 1000);
};

// Démarrage automatique si on arrive sur la page et qu'un fichier est en cours
onMounted(() => {
    if (processingFile.value && ['pending', 'processing'].includes(processingFile.value.status)) {
        startPolling(processingFile.value.id);
    }
});

// Nettoyage si on quitte la page
onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});

// --- UPLOAD ---
const isDragging = ref(false);
const fileInput = ref(null); // Référence au DOM input
const formUpload = useForm({ file: null });

const handleFile = (e) => {
    const files = e.target.files || e.dataTransfer.files;
    if (files.length) {
        formUpload.file = files[0];
        
        // Animation visuelle
        gsap.to('.upload-card', { scale: 0.98, duration: 0.1, yoyo: true, repeat: 1 });

        formUpload.post('/upload', {
            preserveScroll: true,
            onSuccess: (page) => {
                // Le serveur a répondu "OK, Job lancé". 
                // On récupère le fichier créé par le contrôleur et on lance le polling.
                if (page.props.currentFile) {
                    processingFile.value = page.props.currentFile;
                    startPolling(page.props.currentFile.id);
                }
            },
            onError: () => {
                alert("Erreur upload. Vérifiez que c'est bien un PDF.");
            }
        });
    }
};

// --- LOGIQUE FILTRES & CRUD (Identique à avant) ---
const searchQuery = ref('');
const filteredStudents = computed(() => {
    if (!props.students) return [];
    if (!searchQuery.value) return props.students;
    return props.students.filter(s => s.toLowerCase().includes(searchQuery.value.toLowerCase()));
});

const selectStudent = (name) => {
    router.get('/', { student: name }, { preserveState: true, preserveScroll: true });
};

const showEditModal = ref(false);
const formEdit = useForm({ id: null, student_name: '', is_present: false, date: '' });
const openEdit = (slot) => {
    formEdit.id = slot.id; formEdit.student_name = slot.student_name;
    formEdit.is_present = Boolean(slot.is_present); formEdit.date = slot.date.split('T')[0];
    showEditModal.value = true;
};
const submitEdit = () => { formEdit.put(`/slot/${formEdit.id}`, { preserveScroll: true, onSuccess: () => showEditModal.value = false }); };
const deleteSlot = (id) => { if(confirm('Supprimer ?')) router.delete(`/slot/${id}`, { preserveScroll: true }); };
const formatDate = (d) => new Date(d).toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'long' });
const getStatusColor = (p) => p ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : 'bg-rose-100 text-rose-700 border-rose-200';
</script>
<template>
    <MainLayout>
        <div class="flex h-[calc(100vh-64px)] overflow-hidden">
            
            <div class="w-80 bg-white border-r border-slate-200 flex flex-col z-20 shadow-lg shrink-0">
                <div class="p-4 border-b border-slate-100">
                    <h2 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 flex items-center gap-2">
                        <PhUsers weight="bold"/> Apprenants ({{ students ? students.length : 0 }})
                    </h2>
                    <div class="relative">
                        <PhMagnifyingGlass class="absolute left-3 top-2.5 text-slate-400" />
                        <input v-model="searchQuery" type="text" placeholder="Filtrer..." class="w-full pl-9 pr-4 py-2 rounded-lg bg-slate-50 border-transparent focus:bg-white focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    </div>
                </div>
                <div class="flex-1 overflow-y-auto p-2">
                    <button v-for="student in filteredStudents" :key="student" @click="selectStudent(student)"
                        class="w-full text-left px-4 py-3 rounded-lg text-sm font-medium transition-colors flex justify-between items-center group"
                        :class="selectedStudent === student ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-50'">
                        <span class="truncate">{{ student }}</span>
                        <PhCaretRight v-if="selectedStudent === student" weight="bold" class="text-indigo-500"/>
                    </button>
                </div>
                <div class="p-4 border-t border-slate-100 bg-slate-50">
                    <button @click="$refs.fileInput.click()" class="w-full py-2 bg-white border border-slate-300 rounded-lg text-sm font-bold text-slate-600 hover:border-indigo-500 hover:text-indigo-600 shadow-sm flex items-center justify-center gap-2 transition-all">
                        <PhCloudArrowUp weight="bold"/> Ajouter un fichier
                    </button>
                    <input type="file" ref="fileInput" class="hidden" accept=".pdf" @change="handleFile" />
                </div>
            </div>

            <div class="flex-1 overflow-y-auto bg-[#f8fafc] p-8 relative">
                
                <!-- <div v-if="localActiveFile && localActiveFile.status !== 'completed' && localActiveFile.status !== 'failed'" class="absolute top-0 left-0 right-0 z-50 animate-in slide-in-from-top duration-300">
                   <div class="bg-white border-b border-indigo-100 shadow-md p-4 flex items-center gap-4">
                       <PhSpinner class="animate-spin text-indigo-600" :size="24" />
                       <div class="flex-1">
                           <div class="flex justify-between text-xs font-bold text-indigo-900 mb-1">
                               <span>Analyse IA en cours...</span>
                               <span>{{ progressPercent }}% (Page {{ localActiveFile.processed_pages }}/{{ localActiveFile.total_pages }})</span>
                           </div>
                           <div class="h-2 bg-indigo-100 rounded-full overflow-hidden">
                               <div class="h-full bg-indigo-600 transition-all duration-300" :style="{ width: progressPercent + '%' }"></div>
                           </div>
                       </div>
                   </div>
                </div> -->

                <div v-if="localActiveFile && localActiveFile.status === 'failed'" class="mb-8 bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <PhWarning :size="24" weight="fill"/>
                        <span class="font-medium">Le traitement a échoué. Vérifiez le fichier.</span>
                    </div>
                    <button @click="localActiveFile = null" class="text-sm underline">Fermer</button>
                </div>

                <div v-if="selectedStudent" class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-slate-800">{{ selectedStudent }}</h1>
                        <p class="text-slate-500">Vue détaillée des présences</p>
                    </div>
                    <a :href="`/export-pdf?student=${selectedStudent}`" target="_blank" class="flex items-center gap-2 bg-rose-600 hover:bg-rose-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-rose-200 transition-all hover:-translate-y-0.5">
                        <PhFilePdf :size="20" weight="fill"/> Exporter PDF
                    </a>
                </div>

                <div v-if="!selectedStudent && (!students || students.length === 0)" class="h-full flex flex-col items-center justify-center opacity-40 -mt-10">
                    <PhCloudArrowUp :size="80" class="text-slate-300 mb-4" weight="thin"/>
                    <h3 class="text-xl font-medium text-slate-500">Aucun planning chargé</h3>
                    <p class="text-slate-400 max-w-sm text-center">Uploadez un PDF via le bouton dans la barre latérale pour commencer.</p>
                </div>

                <div v-if="selectedStudent && schedule && Object.keys(schedule).length > 0" class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6 pb-20">
                    <div v-for="(daySlots, date) in schedule" :key="date" class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm hover:shadow-md transition-shadow">
                        <div class="border-b border-slate-50 pb-3 mb-3">
                            <span class="text-lg font-bold text-slate-700 capitalize">{{ formatDate(date) }}</span>
                        </div>
                        <div class="space-y-3">
                            <div v-for="slot in daySlots" :key="slot.id" class="p-3 rounded-xl bg-slate-50 border border-transparent hover:border-indigo-100 transition-colors group">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">
                                        {{ slot.period === 'morning' ? 'MATIN' : 'APRÈS-MIDI' }}
                                    </span>
                                    <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button @click="openEdit(slot)" class="p-1.5 bg-white text-indigo-500 hover:text-indigo-700 rounded-lg shadow-sm"><PhPencilSimple weight="bold"/></button>
                                        <button @click="deleteSlot(slot.id)" class="p-1.5 bg-white text-rose-500 hover:text-rose-700 rounded-lg shadow-sm"><PhTrash weight="bold"/></button>
                                    </div>
                                </div>
                                <div @click="openEdit(slot)" class="cursor-pointer select-none flex items-center justify-center py-2 rounded-lg text-xs font-bold border w-full transition-colors"
                                    :class="getStatusColor(slot.is_present)">
                                    <component :is="slot.is_present ? PhCheckCircle : PhXCircle" weight="fill" class="mr-2"/>
                                    {{ slot.is_present ? 'PRÉSENT' : 'ABSENT' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showEditModal" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="showEditModal = false"></div>
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md relative z-10 p-6 space-y-4">
                <h3 class="font-bold text-lg text-slate-800">Modifier le créneau</h3>
                <form @submit.prevent="submitEdit" class="space-y-4">
                    <input type="text" v-model="formEdit.student_name" class="w-full rounded-xl border-slate-200 font-medium">
                    <input type="date" v-model="formEdit.date" class="w-full rounded-xl border-slate-200 font-medium">
                    <div class="grid grid-cols-2 gap-4">
                        <label class="cursor-pointer border-2 border-slate-100 rounded-xl p-3 text-center hover:border-emerald-200" :class="{'bg-emerald-50 border-emerald-500': formEdit.is_present}">
                            <input type="radio" v-model="formEdit.is_present" :value="true" class="hidden"> <span class="text-sm font-bold text-emerald-700">Présent</span>
                        </label>
                        <label class="cursor-pointer border-2 border-slate-100 rounded-xl p-3 text-center hover:border-rose-200" :class="{'bg-rose-50 border-rose-500': !formEdit.is_present}">
                            <input type="radio" v-model="formEdit.is_present" :value="false" class="hidden"> <span class="text-sm font-bold text-rose-700">Absent</span>
                        </label>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showEditModal = false" class="px-4 py-2 text-slate-500 font-bold hover:bg-slate-50 rounded-lg">Annuler</button>
                        <button type="submit" class="px-6 py-2 bg-indigo-600 text-white font-bold rounded-lg hover:bg-indigo-700 shadow-md">Sauvegarder</button>
                    </div>
                </form>
            </div>
        </div>
    </MainLayout>
</template>