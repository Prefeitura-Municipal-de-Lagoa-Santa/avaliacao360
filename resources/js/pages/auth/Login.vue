<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { onMounted, ref, Ref } from 'vue';
// 1. Mude a importação do layout
import AuthLayout from '@/layouts/GuestLayout.vue';

// 2. Defina o novo layout como persistente para esta página
defineOptions({ layout: AuthLayout });

// --- Lógica do Formulário com TypeScript ---
const form = useForm({
    username: '',
    password: '',
    remember: false,
});

// --- Lógica da Animação com TypeScript ---
const loginBox: Ref<HTMLDivElement | null> = ref(null);
const peekContainer: Ref<HTMLDivElement | null> = ref(null);
const alligatorCharacter: Ref<HTMLDivElement | null> = ref(null);
const feDisplacementMap: Ref<SVGElement | null> = ref(null);

let alligatorTimeout: ReturnType<typeof setTimeout> | undefined;
let waterAnimationTimeout: ReturnType<typeof setTimeout> | undefined;

const alligatorAppearanceProbability = 0.000000001; 

const getRandomPosition = (): { x: number, y: number } => {
    if (!peekContainer.value || !loginBox.value) {
        return { x: window.innerWidth / 2, y: window.innerHeight / 2 };
    }
    const margin = 10;
    const peekWidth = peekContainer.value.offsetWidth;
    const peekHeight = peekContainer.value.offsetHeight;
    let x = 0, y = 0, attempts = 0;
    const maxAttempts = 10;
    let isOverlapping = true;

    do {
        x = Math.random() * (window.innerWidth - peekWidth - 2 * margin) + margin;
        y = Math.random() * (window.innerHeight - peekHeight - 2 * margin) + margin;
        const loginRect = loginBox.value.getBoundingClientRect();
        const peekRect = { left: x, top: y, right: x + peekWidth, bottom: y + peekHeight };
        const safetyMargin = 5;
        isOverlapping = !(
            peekRect.right < loginRect.left - safetyMargin ||
            peekRect.left > loginRect.right + safetyMargin ||
            peekRect.bottom < loginRect.top - safetyMargin ||
            peekRect.top > loginRect.bottom + safetyMargin
        );
        attempts++;
    } while (isOverlapping && attempts < maxAttempts);

    return { x, y };
}

const animateWaterImpact = (showEffect: boolean, callback?: () => void): void => {
    if (!feDisplacementMap.value) {
        if (callback) callback();
        return;
    }
    const maxScale = 35;
    const duration = showEffect ? 1200 : 900;
    let startTime: number | null = null;
    const feMap = feDisplacementMap.value;

    const animationStep = (timestamp: number) => {
        if (!startTime) startTime = timestamp;
        const progress = Math.min((timestamp - startTime) / duration, 1);
        let currentScale: number;

        if (showEffect) {
            currentScale = progress < 0.7 ? maxScale * (progress / 0.7) : maxScale * (1 - (progress - 0.7) / 0.3 * 0.3);
        } else {
            const initialScale = parseFloat(feMap.getAttribute('scale') || '0');
            currentScale = initialScale * (1 - progress);
        }

        feMap.setAttribute('scale', Math.max(0, currentScale).toString());

        if (progress < 1) {
            requestAnimationFrame(animationStep);
        } else {
            if (!showEffect) {
                feMap.setAttribute('scale', '0');
            }
            if (callback) callback();
        }
    };
    requestAnimationFrame(animationStep);
}

const triggerAlligatorPeek = (): void => {
    if (!peekContainer.value || !alligatorCharacter.value) return;

    clearTimeout(alligatorTimeout);
    clearTimeout(waterAnimationTimeout);

    const container = peekContainer.value;
    const alligator = alligatorCharacter.value;

    container.classList.remove('hiding');
    const { x, y } = getRandomPosition();
    container.style.left = `${x}px`;
    container.style.top = `${y}px`;

    container.classList.add('show', 'water-active');

    animateWaterImpact(true, () => {
        alligator.style.opacity = '1';
        alligator.style.transform = 'translateY(-15px) scale(1)';

        const visibleDuration = 3500;
        alligatorTimeout = setTimeout(() => {
            container.classList.add('hiding');
            waterAnimationTimeout = setTimeout(() => {
                animateWaterImpact(false, () => {
                    container.classList.remove('show', 'water-active', 'hiding');
                    alligator.style.opacity = '0';
                    alligator.style.transform = 'translateY(35px) scale(0.4)';
                    if (feDisplacementMap.value) {
                        feDisplacementMap.value.setAttribute('scale', '0');
                    }
                });
            }, 200);
        }, visibleDuration);
    });
}

const submit = () => {
    if (Math.random() < alligatorAppearanceProbability) {
        triggerAlligatorPeek();
    }
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

onMounted(() => {
    feDisplacementMap.value = document.querySelector('#realisticWaterFilter feDisplacementMap');
});

</script>

<template>
    <Head title="Login" />

    <!-- Container do Login -->
    <div class="login-container" ref="loginBox">

        <div class="logo-section">
            <img src="/images/logo.png" alt="Logo da Prefeitura de Lagoa Santa" class="w-auto h-25 justify-self-center">
            <div class="logo-text-main">PREFEITURA DE</div>
            <div class="logo-text-main" style="color: #4EC0E6;">LAGOA SANTA</div>
            <div class="logo-text-sub">Sistema de Avaliação de Desempenho</div>
        </div>
        
        <div v-if="form.hasErrors" class="mb-4">
             <div class="font-medium text-red-600">Opa! Algo deu errado.</div>
             <ul class="mt-3 list-disc list-inside text-sm text-red-600">
                <li v-for="(error, key) in form.errors" :key="key">{{ error }}</li>
            </ul>
        </div>
        
        <form @submit.prevent="submit">
            <div class="input-group">
                <label for="username">Usuário</label>
                <input type="username" id="username" v-model="form.username" required autocomplete="username" placeholder="Usuário">
            </div>
            <div class="input-group">
                <label for="password">Senha</label>
                <input type="password" id="password" v-model="form.password" required autocomplete="current-password" placeholder="********">
            </div>
            
            <button type="submit" class="login-button" :disabled="form.processing">Entrar</button>
        </form>

    </div>
    
    <!-- Efeito do Jacaré -->
    <div id="peekEffectContainer" class="peek-effect-container" ref="peekContainer">
        <div id="realistic-water-effect">
             <svg viewBox="0 0 320 240">
                <defs>
                    <filter id="realisticWaterFilter" x="-50%" y="-50%" width="200%" height="200%">
                       <feTurbulence type="fractalNoise" baseFrequency="0.01 0.03" numOctaves="2" seed="2" result="baseTurbulence">
                            <animate attributeName="baseFrequency" dur="12s" keyTimes="0;0.5;1" values="0.01 0.03;0.015 0.035;0.01 0.03" repeatCount="indefinite" />
                        </feTurbulence>
                        <feDisplacementMap in="SourceGraphic" in2="baseTurbulence" scale="0" xChannelSelector="R" yChannelSelector="G" result="displacement"/>
                        <feGaussianBlur in="displacement" stdDeviation="1.5" result="blurredWater"/> 
                        <feSpecularLighting result="specularLight" in="blurredWater" surfaceScale="3" specularConstant="0.7" specularExponent="15" lighting-color="#FFFFFF">
                           <feDistantLight azimuth="235" elevation="60"/>
                        </feSpecularLighting>
                        <feComposite in="specularLight" in2="blurredWater" operator="in" result="specularReflection"/>
                        <feComposite in="SourceGraphic" in2="specularReflection" operator="arithmetic" k1="0" k2="0.2" k3="1" k4="0" result="waterWithHighlight"/>
                    </filter>
                </defs>
                <circle cx="160" cy="120" r="100" class="water-surface-shape"/>
            </svg>
        </div>
        <div id="alligator-character" ref="alligatorCharacter">
             <svg viewBox="0 0 120 80" xmlns="http://www.w3.org/2000/svg">
                <g class="alligator-texture"><path d="M30 45 Q 35 42, 40 45" /><path d="M50 42 Q 55 39, 60 42" /><path d="M70 45 Q 75 42, 80 45" /><path d="M90 42 Q 95 39, 100 42" /></g>
                <path class="alligator-skin" d="M0 60 C 10 40, 30 35, 60 40 C 90 35, 110 40, 120 60 L120 80 L0 80 Z" /><path class="alligator-snout-ridge" d="M10 60 Q 20 50, 30 52 Q 40 48, 50 52 Q 60 48, 70 52 Q 80 48, 90 52 Q 100 50, 110 60 L105 65 Q 95 55, 85 58 Q 75 53, 65 58 Q 55 53, 45 58 Q 35 55, 25 60 L10 60 Z" />
                <g class="alligator-teeth"><path d="M15 58 L18 62 L21 58 Z" /><path d="M25 59 L28 63 L31 59 Z" /><path d="M90 59 L93 63 L96 59 Z" /><path d="M100 58 L103 62 L106 58 Z" /></g>
                <circle class="alligator-eye-white" cx="35" cy="42" r="12"/><path class="alligator-skin" d="M23 45 A15 15 0 0 1 47 45 Z" /><circle class="alligator-pupil" cx="38" cy="42" r="5"/><circle class="alligator-eye-shine" cx="40" cy="40" r="2"/>
                <circle class="alligator-eye-white" cx="85" cy="42" r="12"/><path class="alligator-skin" d="M73 45 A15 15 0 0 1 97 45 Z" /><circle class="alligator-pupil" cx="82" cy="42" r="5"/><circle class="alligator-eye-shine" cx="80" cy="40" r="2"/>
                <ellipse class="alligator-nostril" cx="15" cy="56" rx="4" ry="2.5"/><ellipse class="alligator-nostril" cx="105" cy="56" rx="4" ry="2.5"/>
            </svg>
        </div>
    </div>
</template>

<style scoped>
/* A regra do 'body' foi REMOVIDA daqui. O AuthLayout agora cuida disso. */

.login-container {
    background-color: #ffffff;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    width: 100%;
    max-width: 450px;
    padding: 40px;
    text-align: center;
    z-index: 10;
    position: relative;
}
.logo-section { margin-bottom: 30px; }
.logo-text-main { font-size: 2.25rem; font-weight: 700; color: #2E3A6C; line-height: 1.1; }
.logo-text-sub { font-size: 1rem; color: #555; margin-top: 5px; }
.input-group { margin-bottom: 20px; text-align: left; }
.input-group label { display: block; font-size: 0.9rem; font-weight: 500; color: #333; margin-bottom: 8px; }
.input-group input { width: 100%; padding: 12px 15px; border: 1px solid #e0e0e0; border-radius: 10px; font-size: 1rem; color: #333; outline: none; transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out; }
.input-group input:focus { border-color: #4EC0E6; box-shadow: 0 0 0 3px rgba(78, 192, 230, 0.2); }
.login-button { width: 100%; padding: 14px 20px; background-color: #2E3A6C; color: white; border-radius: 10px; font-weight: 600; font-size: 1.1rem; cursor: pointer; transition: background-color 0.2s ease-in-out, transform 0.1s ease-in-out; box-shadow: 0 5px 15px rgba(46, 58, 108, 0.3); }
.login-button:hover { background-color: #1a2342; transform: translateY(-2px); }
.links { margin-top: 25px; font-size: 0.9rem; }
.links a { color: #4EC0E6; text-decoration: none; font-weight: 500; transition: color 0.2s ease-in-out; }
.links a:hover { color: #0284c7; }

.peek-effect-container {
    position: fixed;
    width: 320px; 
    height: 240px; 
    opacity: 0;
    transition: opacity 0.3s ease-out, transform 0.3s ease-out;
    z-index: 5;
    display: flex;
    justify-content: center;
    align-items: center;
    transform: scale(0.9) translateY(5px); 
    pointer-events: none;
}
.peek-effect-container.show {
    opacity: 1;
    transform: scale(1) translateY(0);
}

#realistic-water-effect {
    width: 100%;
    height: 100%;
}
#realistic-water-effect svg {
    width: 100%;
    height: 100%;
    overflow: visible; 
}

.water-surface-shape {
    fill: rgba(78, 192, 230, 0.1);
    filter: url(#realisticWaterFilter);
    opacity: 0; 
    transition: opacity 0.3s ease-in-out;
}
.peek-effect-container.water-active .water-surface-shape {
    opacity: 1; 
}

#alligator-character {
    width: 110px;
    height: auto;
    position: absolute;
    opacity: 0;
    transform: translateY(35px) scale(0.4); 
    transition: opacity 0.6s 0.5s cubic-bezier(0.34, 1.56, 0.64, 1), transform 0.6s 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
    z-index: 7;
}

.peek-effect-container.hiding #alligator-character {
    opacity: 0 !important;
    transform: translateY(45px) scale(0.3) !important; 
    transition: opacity 0.4s ease-in, transform 0.4s ease-in !important;
}

.alligator-skin { fill: #38761D; }
.alligator-snout-ridge { fill: #274E13; }
.alligator-eye-white { fill: #F0F0F0; }
.alligator-pupil { fill: #1c1c1c; }
.alligator-eye-shine { fill: #FFFFFF; opacity: 0.8; }
.alligator-nostril { fill: #274E13; }
.alligator-teeth { fill: #E0E0E0; }
.alligator-texture { stroke: #274E13; stroke-width: 0.5; fill: none; }
</style>
