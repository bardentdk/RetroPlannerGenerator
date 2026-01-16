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
    schedule: Object,       // Données du planning
    students: Array,        // Liste pour la sidebar
    selectedStudent: String,// Étudiant sélectionné
    currentFile: Object,    // Fichier en cours
    errors: Object,
    flash: Object
});

// --- SYSTÈME DE POLLING ---
const processingFile = ref(props.currentFile); 
const progressPercent = ref(0);
let pollInterval = null;

const startPolling = (fileId) => {
    if (pollInterval) clearInterval(pollInterval);
    
    // Initialisation visuelle
    if (!processingFile.value) {
        processingFile.value = { id: fileId, status: 'pending', processed_pages: 0, total_pages: 0 };
    }

    pollInterval = setInterval(async () => {
        try {
            // Anti-cache pour avoir la vraie progression
            const timestamp = Date.now();
            const res = await fetch(`/upload/status/${fileId}?t=${timestamp}`);
            
            if (!res.ok) return;
            
            const data = await res.json();
            processingFile.value = data;
            
            // Calcul pourcentage
            if (data.total_pages > 0) {
                progressPercent.value = Math.round((data.processed_pages / data.total_pages) * 100);
            }

            // Gestion Fin de traitement
            if (data.status === 'completed' || data.status === 'failed') {
                clearInterval(pollInterval);
                
                if (data.status === 'completed') {
                    progressPercent.value = 100;
                    
                    // --- CORRECTION ICI ---
                    // On attend 1s puis on force un rechargement complet des données (Sidebar + Planning)
                    setTimeout(() => {
                        router.reload({
                            preserveScroll: true, // On ne remonte pas tout en haut
                            preserveState: true,  // On garde le champ de recherche rempli
                            onSuccess: () => {
                                processingFile.value = null; // On cache la barre APRES le rechargement
                            }
                        });
                    }, 1000);
                }
            }
        } catch (e) {
            console.error("Polling error:", e);
        }
    }, 1000);
};

// Démarrage auto
onMounted(() => {
    if (processingFile.value && ['pending', 'processing'].includes(processingFile.value.status)) {
        startPolling(processingFile.value.id);
    }
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});

// --- UPLOAD ---
const fileInput = ref(null);
const formUpload = useForm({ file: null });

const handleFile = (e) => {
    const files = e.target.files || e.dataTransfer.files;
    if (files.length) {
        formUpload.file = files[0];
        
        gsap.to('.upload-area', { scale: 0.95, duration: 0.1, yoyo: true, repeat: 1 });

        formUpload.post('/upload', {
            preserveScroll: true,
            onSuccess: (page) => {
                if (page.props.currentFile) {
                    processingFile.value = page.props.currentFile;
                    startPolling(page.props.currentFile.id);
                }
            },
            onError: () => alert("Erreur upload PDF.")
        });
    }
};

// --- LOGIQUE SIDEBAR ---
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
                    <button @click="$refs.fileInput.click()" class="w-full py-2 bg-white border border-slate-300 rounded-lg text-sm font-bold text-slate-600 hover:border-indigo-500 hover:text-indigo-600 shadow-sm flex items-center justify-center gap-2 transition-all upload-area">
                        <PhCloudArrowUp weight="bold"/> Ajouter PDF
                    </button>
                    <input type="file" ref="fileInput" class="hidden" accept=".pdf" @change="handleFile" />
                </div>
            </div>

            <div class="flex-1 overflow-y-auto bg-[#f8fafc] p-8 relative">
                
                <div v-if="processingFile && processingFile.status !== 'completed' && processingFile.status !== 'failed'" class="absolute top-0 left-0 right-0 z-50 animate-in slide-in-from-top duration-300">
                   <div class="bg-white border-b border-indigo-100 shadow-md p-4 flex items-center gap-4">
                       <PhSpinner class="animate-spin text-indigo-600" :size="24" />
                       <div class="flex-1">
                           <div class="flex justify-between text-xs font-bold text-indigo-900 mb-1">
                               <span>ANALYSE IA EN COURS...</span>
                               <span>{{ progressPercent }}% (Page {{ processingFile.processed_pages }}/{{ processingFile.total_pages }})</span>
                           </div>
                           <div class="h-2 bg-indigo-100 rounded-full overflow-hidden">
                               <div class="h-full bg-indigo-600 transition-all duration-300" :style="{ width: progressPercent + '%' }"></div>
                           </div>
                       </div>
                   </div>
                </div>

                <div v-if="processingFile && processingFile.status === 'failed'" class="mb-8 bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <PhWarning :size="24" weight="fill"/>
                        <span class="font-medium">Échec du traitement. Vérifiez le fichier.</span>
                    </div>
                    <button @click="processingFile = null" class="text-sm underline">Fermer</button>
                </div>

                <div v-if="selectedStudent" class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-slate-800">{{ selectedStudent }}</h1>
                        <p class="text-slate-500">Planning de formation</p>
                    </div>
                    <a :href="`/export-pdf?student=${selectedStudent}`" target="_blank" class="flex items-center gap-2 bg-rose-600 hover:bg-rose-700 text-white px-5 py-2.5 rounded-xl font-bold shadow-lg shadow-rose-200 transition-all hover:-translate-y-0.5">
                        <PhFilePdf :size="20" weight="fill"/> Exporter PDF
                    </a>
                </div>

                <div v-if="!selectedStudent && (!students || students.length === 0)" class="h-full flex flex-col items-center justify-center opacity-40 -mt-10">
                    <PhCloudArrowUp :size="80" class="text-slate-300 mb-4" weight="thin"/>
                    <h3 class="text-xl font-medium text-slate-500">Aucun planning chargé</h3>
                    <p class="text-slate-400 mt-2">Uploadez un PDF pour commencer.</p>
                </div>
                
                <div v-else-if="!selectedStudent" class="h-full flex flex-col items-center justify-center opacity-40 -mt-10">
                    <PhUsers :size="80" class="text-slate-300 mb-4" weight="thin"/>
                    <h3 class="text-xl font-medium text-slate-500">Sélectionnez un apprenant</h3>
                    <p class="text-slate-400">Cliquez sur un nom dans la liste à gauche.</p>
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