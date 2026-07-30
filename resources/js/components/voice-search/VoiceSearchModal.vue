<template>
  <Teleport to="body">
    <div
      v-if="modelValue"
      class="voice-search-overlay"
      :class="{ 'voice-search-overlay--mobile': isMobile }"
      @click.self="close"
    >
      <div
        class="voice-search-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="voice-search-title"
        @click.stop
      >
        <div class="voice-search-modal__grabber" aria-hidden="true" />

        <div class="voice-search-modal__head">
          <div>
            <h6 id="voice-search-title" class="voice-search-modal__title">Voice Search</h6>
            <p class="voice-search-modal__subtitle">Speak in Arabic, English, or both</p>
          </div>
          <button type="button" class="voice-search-modal__close" aria-label="Close" @click="close">
            <i class="ri-close-line"></i>
          </button>
        </div>

        <div class="voice-search-modal__body">
          <div class="voice-search-stage" :class="`is-${status}`">
            <div class="voice-mic-wrap">
              <span class="voice-mic-pulse" aria-hidden="true" />
              <span class="voice-mic-pulse voice-mic-pulse--delay" aria-hidden="true" />
              <button
                type="button"
                class="voice-mic-btn"
                :disabled="status === 'processing' || !speechSupported"
                :aria-label="status === 'listening' ? 'Stop listening' : 'Start listening'"
                @click="toggleListening"
              >
                <i :class="status === 'listening' ? 'ri-mic-fill' : 'ri-mic-line'"></i>
              </button>
            </div>

            <VoiceWaveAnimation :active="status === 'listening'" />

            <div class="voice-status-row">
              <span class="voice-status-dot" :class="`is-${status}`" />
              <span class="voice-status-text">{{ statusLabel }}</span>
            </div>

            <p v-if="!speechSupported" class="voice-unsupported">
              Voice search is not supported in this browser. Try Chrome or Safari.
            </p>
          </div>

          <div class="voice-transcript-card">
            <div class="voice-transcript-card__label">Transcript</div>
            <p class="voice-transcript-card__text">
              {{ displayTranscript || 'Say something like “2 bedroom apartment in Reem Island under 1.5 million”' }}
            </p>
          </div>

          <p v-if="errorMessage" class="voice-error">{{ errorMessage }}</p>
        </div>

        <div class="voice-search-modal__footer">
          <button type="button" class="voice-btn voice-btn--ghost" :disabled="!transcript && !interimTranscript" @click="clearTranscript">
            Clear
          </button>
          <button
            type="button"
            class="voice-btn voice-btn--primary"
            :disabled="!canSearch || status === 'processing'"
            @click="submitTranscript"
          >
            <span v-if="status === 'processing'">Searching…</span>
            <span v-else>Search</span>
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import api from '@/plugins/axios';
import VoiceWaveAnimation from './VoiceWaveAnimation.vue';

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  existingFilters: {
    type: Object,
    default: () => ({}),
  },
  isMobile: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['update:modelValue', 'filters-applied', 'close']);

const status = ref('idle'); // idle | listening | processing | completed | error
const transcript = ref('');
const interimTranscript = ref('');
const errorMessage = ref('');
const speechSupported = ref(false);

let recognition = null;
let autoSubmitTimer = null;

const displayTranscript = computed(() => {
  const finalText = transcript.value.trim();
  const interim = interimTranscript.value.trim();
  if (finalText && interim) return `${finalText} ${interim}`;
  return finalText || interim;
});

const canSearch = computed(() => displayTranscript.value.trim().length > 0);

const statusLabel = computed(() => {
  switch (status.value) {
    case 'listening':
      return 'Listening…';
    case 'processing':
      return 'Understanding your search…';
    case 'completed':
      return 'Search applied';
    case 'error':
      return 'Something went wrong';
    default:
      return speechSupported.value ? 'Tap the mic to start' : 'Unavailable';
  }
});

function getSpeechRecognitionCtor() {
  if (typeof window === 'undefined') return null;
  return window.SpeechRecognition || window.webkitSpeechRecognition || null;
}

function initRecognition() {
  const Ctor = getSpeechRecognitionCtor();
  speechSupported.value = !!Ctor;
  if (!Ctor) return;

  recognition = new Ctor();
  recognition.continuous = true;
  recognition.interimResults = true;
  // Prefer Arabic + English; browsers typically pick based on spoken language.
  recognition.lang = 'ar-AE';
  recognition.maxAlternatives = 1;

  recognition.onstart = () => {
    status.value = 'listening';
    errorMessage.value = '';
  };

  recognition.onresult = (event) => {
    let interim = '';
    let finalChunk = '';
    for (let i = event.resultIndex; i < event.results.length; i += 1) {
      const result = event.results[i];
      const text = result[0]?.transcript || '';
      if (result.isFinal) {
        finalChunk += `${text} `;
      } else {
        interim += text;
      }
    }
    if (finalChunk.trim()) {
      transcript.value = `${transcript.value} ${finalChunk}`.replace(/\s+/g, ' ').trim();
      scheduleAutoSubmit();
    }
    interimTranscript.value = interim;
  };

  recognition.onerror = (event) => {
    const code = event?.error || 'unknown';
    if (code === 'aborted' || code === 'no-speech') {
      if (status.value === 'listening') status.value = 'idle';
      return;
    }
    status.value = 'error';
    errorMessage.value =
      code === 'not-allowed'
        ? 'Microphone permission denied. Please allow mic access and try again.'
        : 'Could not capture speech. Please try again.';
    stopListening();
  };

  recognition.onend = () => {
    if (status.value === 'listening') {
      status.value = transcript.value.trim() ? 'idle' : 'idle';
    }
  };
}

function startListening() {
  if (!recognition) initRecognition();
  if (!recognition) {
    status.value = 'error';
    errorMessage.value = 'Voice search is not supported in this browser.';
    return;
  }
  errorMessage.value = '';
  interimTranscript.value = '';
  try {
    recognition.lang = detectPreferredLang(displayTranscript.value);
    recognition.start();
    status.value = 'listening';
  } catch (e) {
    // Already started
    status.value = 'listening';
  }
}

function stopListening() {
  clearAutoSubmit();
  try {
    recognition?.stop();
  } catch (_) {
    /* ignore */
  }
  if (status.value === 'listening') {
    status.value = 'idle';
  }
}

function toggleListening() {
  if (status.value === 'listening') {
    stopListening();
    if (canSearch.value) submitTranscript();
    return;
  }
  startListening();
}

function detectPreferredLang(text) {
  const hasArabic = /[\u0600-\u06FF]/.test(text || '');
  const hasLatin = /[A-Za-z]/.test(text || '');
  if (hasArabic && !hasLatin) return 'ar-AE';
  if (hasLatin && !hasArabic) return 'en-US';
  // Mixed or empty: Arabic UAE covers Arabic + English well on Chrome
  return 'ar-AE';
}

function clearTranscript() {
  clearAutoSubmit();
  transcript.value = '';
  interimTranscript.value = '';
  errorMessage.value = '';
  if (status.value !== 'listening') status.value = 'idle';
}

function clearAutoSubmit() {
  if (autoSubmitTimer) {
    clearTimeout(autoSubmitTimer);
    autoSubmitTimer = null;
  }
}

function scheduleAutoSubmit() {
  clearAutoSubmit();
  // After a short pause of final speech, auto-run search.
  autoSubmitTimer = setTimeout(() => {
    if (status.value === 'listening') stopListening();
    if (canSearch.value) submitTranscript();
  }, 1100);
}

async function submitTranscript() {
  const text = displayTranscript.value.trim();
  if (!text || status.value === 'processing') return;

  clearAutoSubmit();
  stopListening();
  status.value = 'processing';
  errorMessage.value = '';

  try {
    const { data } = await api.post('/listings/voice-search', {
      transcript: text,
      existing_filters: props.existingFilters || {},
    });

    const payload = data?.data || {};
    emit('filters-applied', {
      filters: payload.filters || {},
      query_params: payload.query_params || {},
      language: payload.language,
      transcript: payload.transcript || text,
      count: payload.count,
    });
    status.value = 'completed';
    // Close shortly after success for smooth UX
    setTimeout(() => close(), 450);
  } catch (err) {
    status.value = 'error';
    const msg =
      err?.response?.data?.message ||
      err?.message ||
      'Voice search failed. Please try again.';
    errorMessage.value = msg;
  }
}

function close() {
  clearAutoSubmit();
  stopListening();
  emit('update:modelValue', false);
  emit('close');
}

watch(
  () => props.modelValue,
  (open) => {
    if (typeof document !== 'undefined') {
      document.body.classList.toggle('voice-search-open', open);
    }
    if (open) {
      if (!recognition) initRecognition();
      status.value = 'idle';
      errorMessage.value = '';
      transcript.value = '';
      interimTranscript.value = '';
      // Auto-start listening when modal opens
      setTimeout(() => {
        if (props.modelValue && speechSupported.value) startListening();
      }, 250);
    } else {
      clearAutoSubmit();
      stopListening();
      interimTranscript.value = '';
    }
  }
);

onBeforeUnmount(() => {
  clearAutoSubmit();
  stopListening();
  recognition = null;
  if (typeof document !== 'undefined') {
    document.body.classList.remove('voice-search-open');
  }
});
</script>

<style>
/* Global: lock scroll while voice modal is open (esp. mobile) */
body.voice-search-open {
  overflow: hidden;
  touch-action: none;
}
</style>

<style scoped>
.voice-search-overlay {
  position: fixed;
  inset: 0;
  z-index: 100100;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
  background: rgba(11, 7, 54, 0.45);
  backdrop-filter: blur(4px);
}

.voice-search-overlay--mobile {
  align-items: flex-end;
  padding: 0;
}

.voice-search-modal {
  width: min(440px, 100%);
  background: #fff;
  border-radius: 20px;
  box-shadow: 0 24px 60px rgba(11, 7, 54, 0.22);
  overflow: hidden;
  animation: voice-modal-in 0.22s ease-out;
}

.voice-search-overlay--mobile .voice-search-modal {
  width: 100%;
  max-height: 92dvh;
  border-radius: 20px 20px 0 0;
  animation: voice-sheet-up 0.24s ease-out;
}

.voice-search-modal__grabber {
  display: none;
  width: 42px;
  height: 4px;
  border-radius: 999px;
  background: #dbe3ef;
  margin: 10px auto 0;
}

.voice-search-overlay--mobile .voice-search-modal__grabber {
  display: block;
}

.voice-search-modal__head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 18px 18px 8px;
}

.voice-search-modal__title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 700;
  color: #0b0736;
}

.voice-search-modal__subtitle {
  margin: 4px 0 0;
  font-size: 0.8rem;
  color: #64748b;
}

.voice-search-modal__close {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  border: 1px solid #e2e8f0;
  background: #fff;
  color: #475569;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}

.voice-search-modal__body {
  padding: 8px 18px 12px;
}

.voice-search-stage {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 14px;
  padding: 12px 0 18px;
}

.voice-mic-wrap {
  position: relative;
  width: 92px;
  height: 92px;
  display: grid;
  place-items: center;
}

.voice-mic-pulse {
  position: absolute;
  inset: 0;
  border-radius: 50%;
  background: rgba(115, 62, 135, 0.18);
  opacity: 0;
}

.voice-search-stage.is-listening .voice-mic-pulse {
  animation: voice-ring 1.6s ease-out infinite;
}

.voice-mic-pulse--delay {
  animation-delay: 0.45s !important;
}

.voice-mic-btn {
  position: relative;
  z-index: 1;
  width: 72px;
  height: 72px;
  border: none;
  border-radius: 50%;
  background: linear-gradient(145deg, #733e87 0%, #0b0736 100%);
  color: #fff;
  font-size: 28px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  box-shadow: 0 10px 24px rgba(115, 62, 135, 0.35);
  transition: transform 0.15s ease, box-shadow 0.15s ease;
}

.voice-mic-btn:hover:not(:disabled) {
  transform: scale(1.04);
}

.voice-mic-btn:disabled {
  opacity: 0.7;
  cursor: not-allowed;
}

.voice-search-stage.is-listening .voice-mic-btn {
  background: linear-gradient(145deg, #b60f1c 0%, #733e87 100%);
}

.voice-status-row {
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.voice-status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #94a3b8;
}

.voice-status-dot.is-listening {
  background: #ef4444;
  box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.5);
  animation: voice-dot-blink 1s infinite;
}

.voice-status-dot.is-processing {
  background: #733e87;
}

.voice-status-dot.is-completed {
  background: #22c55e;
}

.voice-status-dot.is-error {
  background: #ef4444;
}

.voice-status-text {
  font-size: 0.9rem;
  font-weight: 600;
  color: #0b0736;
}

.voice-unsupported,
.voice-error {
  margin: 0;
  text-align: center;
  font-size: 0.8rem;
  color: #b91c1c;
  line-height: 1.4;
}

.voice-transcript-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  padding: 12px 14px;
  min-height: 88px;
}

.voice-transcript-card__label {
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #94a3b8;
  margin-bottom: 6px;
}

.voice-transcript-card__text {
  margin: 0;
  font-size: 0.95rem;
  line-height: 1.45;
  color: #0b0736;
  word-break: break-word;
}

.voice-search-modal__footer {
  display: flex;
  gap: 10px;
  padding: 12px 18px calc(16px + env(safe-area-inset-bottom, 0px));
  border-top: 1px solid #eef2f7;
}

.voice-btn {
  flex: 1;
  min-height: 44px;
  border-radius: 12px;
  font-weight: 600;
  font-size: 0.9rem;
  cursor: pointer;
  border: 1px solid transparent;
}

.voice-btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.voice-btn--ghost {
  background: #fff;
  border-color: #e2e8f0;
  color: #475569;
}

.voice-btn--primary {
  background: #733e87;
  color: #fff;
}

@keyframes voice-modal-in {
  from {
    opacity: 0;
    transform: translateY(8px) scale(0.98);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

@keyframes voice-sheet-up {
  from {
    transform: translateY(100%);
    opacity: 0.85;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

@keyframes voice-ring {
  0% {
    transform: scale(0.85);
    opacity: 0.55;
  }
  100% {
    transform: scale(1.35);
    opacity: 0;
  }
}

@keyframes voice-dot-blink {
  0%,
  100% {
    box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.45);
  }
  50% {
    box-shadow: 0 0 0 6px rgba(239, 68, 68, 0);
  }
}
</style>
