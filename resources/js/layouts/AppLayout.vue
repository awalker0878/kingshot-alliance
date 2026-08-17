<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AllianceCrest from '@/components/game/AllianceCrest.vue';
import GovernorPlaque from '@/components/game/GovernorPlaque.vue';
import LocaleSwitcher from '@/components/navigation/LocaleSwitcher.vue';
import NavIcon from '@/components/navigation/NavIcon.vue';
import { useLocale } from '@/localization';

type NavIconName = 'dashboard'|'alliance'|'events'|'roster'|'recruitment'|'content'|'contributions'|'kingdom'|'transfers'|'integrations'|'profile';
type NavigationItem = { label:string; href:string; icon:NavIconName; allianceScoped?:boolean; exact?:boolean };
type PlayerContextPlayer = { id:string; name:string; gamePlayerId:string|null; kingdomNumber:number|null };
type SharedPlayerContext = { activePlayerId:string|null; players:PlayerContextPlayer[] };
const props=withDefaults(defineProps<{user:{name:string;email?:string};playerAllianceName?:string|null;hasPlayerAlliance?:boolean}>(),{playerAllianceName:null,hasPlayerAlliance:false});
const { t }=useLocale(); const page=usePage(); const mobileOpen=ref(false);
const sharedPlayerContext=computed<SharedPlayerContext>(()=>((page.props as Record<string,unknown>).playerContext as SharedPlayerContext|undefined)??{activePlayerId:null,players:[]});
const activeGovernor=computed(()=>sharedPlayerContext.value.players.find(p=>p.id===sharedPlayerContext.value.activePlayerId)??sharedPlayerContext.value.players[0]??null);
const currentPath=computed(()=>page.url.split('?')[0]?.replace(/\/+$/,'')||'/');
const rooms:NavigationItem[]=[
 {label:'Command Overview',href:'/dashboard',icon:'dashboard',exact:true},
 {label:'Alliance Hall',href:'/alliance',icon:'alliance',allianceScoped:true,exact:true},
 {label:'Recruitment Hall',href:'/alliance/recruitment',icon:'recruitment',allianceScoped:true},
 {label:'Event Command',href:'/events',icon:'events'},
 {label:'Intel Room',href:'/alliance/kingdom-alliances',icon:'kingdom',allianceScoped:true},
 {label:'Alliance Roster',href:'/alliance/roster',icon:'roster',allianceScoped:true},
 {label:'Glory Ledger',href:'/alliance/contributions',icon:'contributions',allianceScoped:true},
 {label:'Kingdom Transfer',href:'/alliance/transfers',icon:'transfers',allianceScoped:true},
 {label:'Noticeboard',href:'/alliance/content',icon:'content',allianceScoped:true},
 {label:'Alliance Connections',href:'/alliance/integrations',icon:'integrations',allianceScoped:true},
];
function isDisabled(i:NavigationItem){return i.allianceScoped===true&&!props.hasPlayerAlliance} function isActive(i:NavigationItem){const h=i.href.replace(/\/+$/,'')||'/';return i.exact?currentPath.value===h:currentPath.value===h||currentPath.value.startsWith(`${h}/`)}
function switchPlayer(id:string){if(id!==sharedPlayerContext.value.activePlayerId) router.post(`/players/${id}/activate`,{},{preserveScroll:true,preserveState:true})}
function logout(){router.delete('/logout')}
</script>
<template>
<a href="#main-content" class="fixed start-4 top-4 z-[80] -translate-y-24 bg-[var(--ks-gold)] px-4 py-2 font-bold text-[#181108] focus:translate-y-0">{{ t('common.skipToContent') }}</a>
<div class="min-h-screen bg-[var(--ks-night)] text-[var(--ks-ivory)]">
  <aside class="fixed inset-y-0 start-0 z-40 hidden w-80 overflow-hidden border-e border-[var(--ks-border-strong)] bg-[#080d0d] lg:flex lg:flex-col">
    <img src="/images/kingshot/realm-command.svg" alt="" class="absolute inset-x-0 bottom-0 h-[36%] w-full object-cover opacity-25" />
    <div class="relative border-b border-[var(--ks-border)] p-5">
      <div class="flex items-center gap-3"><AllianceCrest :name="playerAllianceName || 'Kingshot'" size="md"/><div><div class="ks-display text-xl font-bold text-[var(--ks-gold-bright)]">KINGSHOT</div><div class="text-[.65rem] tracking-[.2em] text-[var(--ks-muted)] uppercase">Alliance Command</div></div></div>
    </div>
    <div class="relative space-y-3 px-4 py-4">
      <GovernorPlaque :name="activeGovernor?.name || user.name" :alliance="playerAllianceName" :kingdom="activeGovernor?.kingdomNumber" />
      <label v-if="sharedPlayerContext.players.length>1" class="block text-[.65rem] font-bold tracking-[.14em] text-[var(--ks-muted)] uppercase">Governor
        <select class="ks-input mt-1" :value="sharedPlayerContext.activePlayerId ?? ''" @change="switchPlayer(($event.target as HTMLSelectElement).value)"><option v-for="p in sharedPlayerContext.players" :key="p.id" :value="p.id">{{ p.name }}<template v-if="p.kingdomNumber"> · K{{ p.kingdomNumber }}</template></option></select>
      </label>
    </div>
    <nav class="relative flex-1 overflow-y-auto px-3 pb-5" aria-label="Command rooms"><div class="mb-2 px-3 text-[.65rem] font-bold tracking-[.17em] text-[var(--ks-gold)] uppercase">Command Rooms</div>
      <div class="space-y-1"><template v-for="room in rooms" :key="room.href"><span v-if="isDisabled(room)" class="flex items-center gap-3 rounded px-3 py-2.5 text-sm text-[var(--ks-muted)] opacity-35"><NavIcon :name="room.icon" class="h-5 w-5"/>{{ room.label }}</span><Link v-else :href="room.href" class="flex items-center gap-3 border border-transparent px-3 py-2.5 font-[var(--ks-font-display)] text-sm transition" :class="isActive(room)?'border-[var(--ks-gold-dark)] bg-[linear-gradient(90deg,#15595b,#0c3738)] text-[var(--ks-gold-bright)]':'text-[var(--ks-muted)] hover:border-[var(--ks-border)] hover:bg-[#111715] hover:text-[var(--ks-ivory)]'"><NavIcon :name="room.icon" class="h-5 w-5"/><span class="flex-1">{{ room.label }}</span><span v-if="isActive(room)" class="text-[var(--ks-gold)]">›</span></Link></template></div>
    </nav>
    <div class="relative border-t border-[var(--ks-border)] p-4"><div class="mb-3 flex items-center justify-between"><Link href="/profile" class="text-sm text-[var(--ks-muted)] hover:text-[var(--ks-gold-bright)]">Governor Account</Link><LocaleSwitcher /></div><button type="button" class="w-full border border-[var(--ks-border)] px-3 py-2 text-sm text-[var(--ks-muted)] hover:border-[var(--ks-border-strong)] hover:text-[var(--ks-ivory)]" @click="logout">Leave the Realm</button></div>
  </aside>
  <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-[var(--ks-border)] bg-[rgba(7,12,13,.94)] px-4 backdrop-blur lg:hidden"><button type="button" class="ks-command-button" @click="mobileOpen=true">☰ Command</button><span class="ks-display text-lg text-[var(--ks-gold-bright)]">KINGSHOT</span><LocaleSwitcher /></header>
  <div v-if="mobileOpen" class="fixed inset-0 z-50 lg:hidden"><button type="button" class="absolute inset-0 bg-black/75" aria-label="Close command rooms" @click="mobileOpen=false"></button><div class="relative h-full w-[88%] max-w-sm overflow-y-auto border-e border-[var(--ks-border-strong)] bg-[#090e0e] p-4"><div class="mb-5 flex items-center justify-between"><div class="ks-display text-xl text-[var(--ks-gold-bright)]">Command Rooms</div><button class="text-2xl" @click="mobileOpen=false">×</button></div><div class="space-y-2"><template v-for="room in rooms" :key="room.href"><Link v-if="!isDisabled(room)" :href="room.href" class="flex items-center gap-3 border border-[var(--ks-border)] px-3 py-3" @click="mobileOpen=false"><NavIcon :name="room.icon" class="h-5 w-5"/>{{ room.label }}</Link></template></div></div></div>
  <main id="main-content" class="relative min-h-screen lg:ps-80"><div class="mx-auto w-full max-w-[112rem] px-4 py-5 sm:px-6 lg:px-7"><slot /></div></main>
</div>
</template>
