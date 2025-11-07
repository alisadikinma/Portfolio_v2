<template>
  <section class="relative h-screen flex items-center justify-center overflow-hidden bg-gradient-to-br from-[#0A0E27] via-[#1a1a2e] to-[#16213E]">
    <!-- Matrix Code Rain (dimmed) -->
    <canvas ref="matrixCanvas" class="absolute inset-0 opacity-10"></canvas>

    <!-- Animated Business Metrics Grid -->
    <div class="absolute inset-0 opacity-20">
      <div class="business-metrics-grid"></div>
    </div>

    <!-- Neural Network (subtle) -->
    <div class="absolute inset-0 opacity-15">
      <svg ref="neuralNetwork" class="w-full h-full" viewBox="0 0 1920 1080" preserveAspectRatio="xMidYMid slice"></svg>
    </div>

    <!-- Gradient Orbs -->
    <div class="absolute top-20 left-20 w-96 h-96 bg-blue-500/20 rounded-full blur-[120px] animate-pulse-slow"></div>
    <div class="absolute bottom-20 right-20 w-96 h-96 bg-blue-500/20 rounded-full blur-[120px] animate-pulse-slow animation-delay-1000"></div>

    <!-- Main Content -->
    <div class="container-custom relative z-10 py-8 md:py-12">
      <div class="max-w-7xl mx-auto px-4">
        
        <!-- Main Grid: Left Content + Right Photo -->
        <div class="grid lg:grid-cols-2 gap-8 lg:gap-12 items-center">
          
          <!-- LEFT: Content -->
          <div class="text-center lg:text-left space-y-4 md:space-y-6">
            
            <!-- Name with Professional Gradient -->
            <h1 class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl xl:text-8xl font-black leading-none animate-fade-in-up">
              <span ref="nameElement" class="block text-gradient-professional" data-text="Ali Sadikin Ma">
                {{ name }}
              </span>
            </h1>

            <!-- Dual Title with Split Animation -->
            <div class="space-y-2 md:space-y-3 animate-fade-in-up animation-delay-200">
              <div class="flex items-center justify-center lg:justify-start gap-2 md:gap-3">
                <div class="h-1 w-8 md:w-12 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-full"></div>
                <h2 class="text-lg sm:text-xl md:text-2xl lg:text-3xl font-black text-blue-400">
                  <span ref="pmTitle" class="dual-typewriter"></span>
                  <span class="typewriter-cursor">|</span>
                </h2>
              </div>
              
              <div class="flex items-center justify-center lg:justify-start gap-2 md:gap-3">
                <div class="h-1 w-8 md:w-12 bg-gradient-to-r from-cyan-500 to-purple-500 rounded-full"></div>
                <h2 class="text-lg sm:text-xl md:text-2xl lg:text-3xl font-black text-cyan-400">
                  <span ref="aiTitle" class="dual-typewriter"></span>
                  <span class="typewriter-cursor animation-delay-100">|</span>
                </h2>
              </div>
            </div>

            <!-- Value Proposition -->
            <p class="text-sm sm:text-base md:text-lg text-gray-300 leading-relaxed max-w-xl mx-auto lg:mx-0 animate-fade-in-up animation-delay-400">
              Transforming businesses through <span class="text-blue-400 font-bold">strategic project leadership</span> and 
              <span class="text-cyan-400 font-bold">AI-powered automation</span>
            </p>

            <!-- Methodology Badges -->
            <div class="space-y-2 animate-fade-in-up animation-delay-600">
              <div class="text-xs font-bold text-gray-400 uppercase tracking-wide">⚙️ Core Expertise</div>
              <div class="flex flex-wrap gap-1.5 md:gap-2 justify-center lg:justify-start">
                <!-- PM Tools -->
                <div class="methodology-badge methodology-pm">
                  <svg class="w-3 h-3 md:w-4 md:h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"></path>
                    <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"></path>
                  </svg>
                  <span class="text-xs">Agile Scrum</span>
                </div>
                <div class="methodology-badge methodology-pm">
                  <svg class="w-3 h-3 md:w-4 md:h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"></path>
                  </svg>
                  <span class="text-xs">Jira</span>
                </div>
                
                <!-- AI Tools -->
                <div class="methodology-badge methodology-ai">
                  <svg class="w-3 h-3 md:w-4 md:h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"></path>
                  </svg>
                  <span class="text-xs">Claude AI</span>
                </div>
                <div class="methodology-badge methodology-ai">
                  <svg class="w-3 h-3 md:w-4 md:h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M13 7H7v6h6V7z"></path>
                    <path fill-rule="evenodd" d="M7 2a1 1 0 012 0v1h2V2a1 1 0 112 0v1h2a2 2 0 012 2v2h1a1 1 0 110 2h-1v2h1a1 1 0 110 2h-1v2a2 2 0 01-2 2h-2v1a1 1 0 11-2 0v-1H9v1a1 1 0 11-2 0v-1H5a2 2 0 01-2-2v-2H2a1 1 0 110-2h1V9H2a1 1 0 010-2h1V5a2 2 0 012-2h2V2zM5 5h10v10H5V5z" clip-rule="evenodd"></path>
                  </svg>
                  <span class="text-xs">n8n</span>
                </div>
                <div class="methodology-badge methodology-ai">
                  <svg class="w-3 h-3 md:w-4 md:h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M12.316 3.051a1 1 0 01.633 1.265l-4 12a1 1 0 11-1.898-.632l4-12a1 1 0 011.265-.633zM5.707 6.293a1 1 0 010 1.414L3.414 10l2.293 2.293a1 1 0 11-1.414 1.414l-3-3a1 1 0 010-1.414l3-3a1 1 0 011.414 0zm8.586 0a1 1 0 011.414 0l3 3a1 1 0 010 1.414l-3 3a1 1 0 11-1.414-1.414L16.586 10l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                  </svg>
                  <span class="text-xs">Full-Stack</span>
                </div>
              </div>
            </div>

            <!-- CTA Buttons -->
            <div class="flex flex-wrap items-center justify-center lg:justify-start gap-2 md:gap-3 animate-fade-in-up animation-delay-800">
              <!-- Primary: Download Resume -->
              <button
                @click="$emit('resume-click')"
                class="cta-professional-primary group"
              >
                <div class="absolute inset-0 bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600"></div>
                <span class="relative z-10 flex items-center gap-2 font-black text-white text-sm md:text-base">
                  <svg class="w-4 h-4 md:w-5 md:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                  </svg>
                  DOWNLOAD RESUME
                </span>
                <div class="absolute inset-0 border-2 border-white/20 rounded-xl opacity-0 group-hover:opacity-100 group-hover:scale-105 transition-all"></div>
              </button>

              <!-- Secondary: Schedule Call -->
              <a
                :href="whatsappUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="cta-professional-secondary group"
              >
                <span class="flex items-center gap-2 font-black text-blue-300 group-hover:text-white relative z-10 text-sm md:text-base">
                  <svg class="w-4 h-4 md:w-5 md:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                  </svg>
                  SCHEDULE CALL
                </span>
              </a>
            </div>

          </div>

          <!-- RIGHT: Professional Photo Card -->
          <div class="relative animate-fade-in-right animation-delay-200">
            
            <!-- Status Badge (Moved from Left - Above Photo) -->
            <div class="absolute -top-3 md:-top-4 left-1/2 -translate-x-1/2 z-20 animate-fade-in-down animation-delay-400">
              <div class="inline-flex items-center gap-2 px-3 md:px-4 py-1.5 md:py-2 glass-cyber rounded-full border border-green-500/50 shadow-xl">
                <div class="relative">
                  <div class="w-2 md:w-2.5 h-2 md:h-2.5 bg-green-500 rounded-full animate-ping absolute"></div>
                  <div class="w-2 md:w-2.5 h-2 md:h-2.5 bg-green-400 rounded-full relative shadow-lg shadow-green-500/80"></div>
                </div>
                <span class="text-[10px] md:text-xs font-black text-green-300 tracking-wide uppercase whitespace-nowrap">
                  AVAILABLE • US REMOTE • AGILE PM + AI
                </span>
              </div>
            </div>

            <div 
              ref="professionalCard"
              class="professional-card-container"
              @mousemove="handleMouseMove"
              @mouseleave="handleMouseLeave"
            >
              <div class="professional-card">
                <!-- Photo -->
                <div class="relative aspect-[3/4] rounded-2xl overflow-hidden bg-gradient-to-br from-gray-900 to-gray-800">
                  <img 
                    v-if="standingPhoto" 
                    :src="standingPhoto" 
                    :alt="name"
                    class="w-full h-full object-cover object-center"
                  />
                  
                  <!-- Professional Grid Overlay -->
                  <div class="absolute inset-0 professional-grid-overlay opacity-20"></div>
                  
                  <!-- Corner Frames (Professional) -->
                  <div class="absolute top-3 md:top-4 left-3 md:left-4 w-10 md:w-12 h-10 md:h-12 border-l-2 md:border-l-3 border-t-2 md:border-t-3 border-blue-400"></div>
                  <div class="absolute top-3 md:top-4 right-3 md:right-4 w-10 md:w-12 h-10 md:h-12 border-r-2 md:border-r-3 border-t-2 md:border-t-3 border-blue-400"></div>
                  <div class="absolute bottom-3 md:bottom-4 left-3 md:left-4 w-10 md:w-12 h-10 md:h-12 border-l-2 md:border-l-3 border-b-2 md:border-b-3 border-cyan-400"></div>
                  <div class="absolute bottom-3 md:bottom-4 right-3 md:right-4 w-10 md:w-12 h-10 md:h-12 border-r-2 md:border-r-3 border-b-2 md:border-b-3 border-cyan-400"></div>
                </div>
              </div>
            </div>

            <!-- Floating Achievement Cards (Desktop Only) -->
            <div class="hidden lg:block absolute -right-6 xl:-right-8 top-1/4 achievement-float animation-delay-600">
              <div class="achievement-card achievement-pm">
                <div class="achievement-icon">
                  <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                  </svg>
                </div>
                <div class="achievement-text">
                  <div class="text-lg font-black text-blue-400">Google</div>
                  <div class="text-xs text-gray-400">PM Certified</div>
                </div>
              </div>
            </div>

            <div class="hidden lg:block absolute -left-6 xl:-left-8 bottom-1/3 achievement-float animation-delay-800">
              <div class="achievement-card achievement-startup">
                <div class="achievement-icon">
                  <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"></path>
                  </svg>
                </div>
                <div class="achievement-text">
                  <div class="text-lg font-black text-orange-400">UN + Alibaba</div>
                  <div class="text-xs text-gray-400">eFounders</div>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>

    <!-- Scroll Indicator (Desktop Only) -->
    <div class="hidden md:block absolute bottom-6 left-1/2 -translate-x-1/2 animate-bounce-slow">
      <svg class="w-6 h-6 md:w-8 md:h-8 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
      </svg>
    </div>
  </section>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'

const props = defineProps({
  standingPhoto: String,
  badges: Array,
  name: { type: String, default: 'Ali Sadikin Ma' },
  titleLine1: String,
  titleLine2: String,
  subtitle: String,
  stats: Array,
  whatsappUrl: String
})

const emit = defineEmits(['resume-click'])

// Refs
const matrixCanvas = ref(null)
const neuralNetwork = ref(null)
const professionalCard = ref(null)
const pmTitle = ref(null)
const aiTitle = ref(null)

let typewriterTimeouts = []

// PM Titles
const pmPhrases = [
  'Google Certified PM',
  'Agile Scrum Master',
  'Sprint Planning Expert',
  '56+ Projects Delivered'
]

// AI Titles
const aiPhrases = [
  'AI Automation Architect',
  '160x Faster Development',
  'Claude AI Specialist',
  '$318K+ Cost Savings'
]

// Dual Typewriter Effect
const dualTypewriter = (element, phrases, startDelay = 0) => {
  let phraseIndex = 0
  let charIndex = 0
  let isDeleting = false
  
  const type = () => {
    if (!element.value) return
    
    const phrase = phrases[phraseIndex]
    
    if (isDeleting) {
      element.value.textContent = phrase.substring(0, charIndex - 1)
      charIndex--
    } else {
      element.value.textContent = phrase.substring(0, charIndex + 1)
      charIndex++
    }
    
    let speed = isDeleting ? 30 : 80
    
    if (!isDeleting && charIndex === phrase.length) {
      speed = 2000
      isDeleting = true
    } else if (isDeleting && charIndex === 0) {
      isDeleting = false
      phraseIndex = (phraseIndex + 1) % phrases.length
      speed = 500
    }
    
    const timeout = setTimeout(type, speed)
    typewriterTimeouts.push(timeout)
  }
  
  const initialTimeout = setTimeout(type, startDelay)
  typewriterTimeouts.push(initialTimeout)
}

// Matrix Effect (simplified)
const initMatrix = () => {
  if (!matrixCanvas.value) return
  
  const canvas = matrixCanvas.value
  const ctx = canvas.getContext('2d')
  
  canvas.width = window.innerWidth
  canvas.height = window.innerHeight
  
  const chars = '01'
  const fontSize = 14
  const columns = canvas.width / fontSize
  const drops = Array(Math.floor(columns)).fill(1)
  
  const draw = () => {
    ctx.fillStyle = 'rgba(0, 0, 0, 0.05)'
    ctx.fillRect(0, 0, canvas.width, canvas.height)
    
    ctx.fillStyle = '#0066FF'
    ctx.font = `${fontSize}px monospace`
    
    for (let i = 0; i < drops.length; i++) {
      const text = chars[Math.floor(Math.random() * chars.length)]
      ctx.fillText(text, i * fontSize, drops[i] * fontSize)
      
      if (drops[i] * fontSize > canvas.height && Math.random() > 0.975) {
        drops[i] = 0
      }
      drops[i]++
    }
  }
  
  setInterval(draw, 50)
}

// Neural Network (simplified)
const initNeuralNetwork = () => {
  if (!neuralNetwork.value) return
  
  const svg = neuralNetwork.value
  
  for (let i = 0; i < 30; i++) {
    const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle')
    circle.setAttribute('cx', Math.random() * 1920)
    circle.setAttribute('cy', Math.random() * 1080)
    circle.setAttribute('r', Math.random() * 2 + 1)
    circle.setAttribute('fill', '#0066FF')
    circle.setAttribute('opacity', Math.random() * 0.3 + 0.2)
    svg.appendChild(circle)
  }
}

// 3D Card Parallax
const handleMouseMove = (e) => {
  if (!professionalCard.value) return
  
  const card = professionalCard.value
  const rect = card.getBoundingClientRect()
  const x = e.clientX - rect.left
  const y = e.clientY - rect.top
  
  const centerX = rect.width / 2
  const centerY = rect.height / 2
  
  const rotateX = (y - centerY) / 20
  const rotateY = (centerX - x) / 20
  
  const cardElement = card.querySelector('.professional-card')
  if (cardElement) {
    cardElement.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`
  }
}

const handleMouseLeave = () => {
  if (!professionalCard.value) return
  const cardElement = professionalCard.value.querySelector('.professional-card')
  if (cardElement) {
    cardElement.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg)'
  }
}

onMounted(() => {
  initMatrix()
  initNeuralNetwork()
  
  setTimeout(() => dualTypewriter(pmTitle, pmPhrases, 0), 600)
  setTimeout(() => dualTypewriter(aiTitle, aiPhrases, 0), 900)
})

onUnmounted(() => {
  typewriterTimeouts.forEach(timeout => clearTimeout(timeout))
})
</script>

<style scoped>
/* Glass Cyber */
.glass-cyber {
  background: rgba(59, 130, 246, 0.05);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
}

/* Professional Gradient */
.text-gradient-professional {
  background: linear-gradient(135deg, #3B82F6 0%, #06B6D4 50%, #8B5CF6 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

/* Business Metrics Grid */
.business-metrics-grid {
  background-image: 
    linear-gradient(rgba(59, 130, 246, 0.1) 1px, transparent 1px),
    linear-gradient(90deg, rgba(59, 130, 246, 0.1) 1px, transparent 1px);
  background-size: 60px 60px;
  animation: gridSlide 30s linear infinite;
}

@keyframes gridSlide {
  0% { background-position: 0 0; }
  100% { background-position: 60px 60px; }
}

/* Typewriter Cursor */
.typewriter-cursor {
  animation: blink 0.7s step-end infinite;
  font-weight: 100;
  margin-left: 2px;
}

@keyframes blink {
  50% { opacity: 0; }
}

/* Methodology Badges */
.methodology-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.375rem 0.75rem;
  border-radius: 0.5rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  backdrop-filter: blur(10px);
  border: 1px solid;
  transition: all 0.3s ease;
  cursor: pointer;
}

.methodology-pm {
  background: rgba(59, 130, 246, 0.1);
  border-color: rgba(59, 130, 246, 0.3);
  color: #60A5FA;
}

.methodology-pm:hover {
  background: rgba(59, 130, 246, 0.2);
  border-color: #3B82F6;
  transform: translateY(-1px);
}

.methodology-ai {
  background: rgba(6, 182, 212, 0.1);
  border-color: rgba(6, 182, 212, 0.3);
  color: #22D3EE;
}

.methodology-ai:hover {
  background: rgba(6, 182, 212, 0.2);
  border-color: #06B6D4;
  transform: translateY(-1px);
}

/* CTA Buttons */
.cta-professional-primary {
  position: relative;
  padding: 0.875rem 1.75rem;
  border-radius: 0.75rem;
  overflow: hidden;
  transition: all 0.3s ease;
  cursor: pointer;
  border: 2px solid transparent;
}

@media (min-width: 768px) {
  .cta-professional-primary {
    padding: 1rem 2rem;
    border-radius: 1rem;
  }
}

.cta-professional-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 20px 60px rgba(59, 130, 246, 0.5);
}

.cta-professional-secondary {
  position: relative;
  padding: 0.875rem 1.75rem;
  background: rgba(59, 130, 246, 0.1);
  border: 2px solid rgba(59, 130, 246, 0.5);
  border-radius: 0.75rem;
  backdrop-filter: blur(10px);
  transition: all 0.3s ease;
  cursor: pointer;
}

@media (min-width: 768px) {
  .cta-professional-secondary {
    padding: 1rem 2rem;
    border-radius: 1rem;
  }
}

.cta-professional-secondary:hover {
  background: rgba(59, 130, 246, 0.2);
  border-color: #3B82F6;
  transform: translateY(-2px);
  box-shadow: 0 20px 60px rgba(59, 130, 246, 0.4);
}

/* Professional Card */
.professional-card-container {
  perspective: 1500px;
}

.professional-card {
  position: relative;
  padding: 1.5rem;
  background: rgba(59, 130, 246, 0.05);
  border: 2px solid rgba(59, 130, 246, 0.2);
  border-radius: 1.5rem;
  backdrop-filter: blur(20px);
  transform-style: preserve-3d;
  transition: transform 0.3s ease;
}

@media (min-width: 768px) {
  .professional-card {
    padding: 2rem;
    border-radius: 2rem;
  }
}

.professional-grid-overlay {
  background-image: 
    linear-gradient(rgba(59, 130, 246, 0.2) 1px, transparent 1px),
    linear-gradient(90deg, rgba(59, 130, 246, 0.2) 1px, transparent 1px);
  background-size: 30px 30px;
}

.border-l-3, .border-r-3, .border-t-3, .border-b-3 {
  border-width: 3px;
}

/* Achievement Cards */
.achievement-float {
  animation: floatAchievement 3s ease-in-out infinite;
}

@keyframes floatAchievement {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-10px); }
}

.achievement-card {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.875rem 1rem;
  background: rgba(0, 0, 0, 0.9);
  backdrop-filter: blur(20px);
  border-radius: 1rem;
  border: 2px solid;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
}

.achievement-pm {
  border-color: rgba(59, 130, 246, 0.5);
}

.achievement-startup {
  border-color: rgba(249, 115, 22, 0.5);
}

.achievement-icon {
  width: 2.25rem;
  height: 2.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 0.5rem;
}

.achievement-text {
  text-align: left;
}

/* Utility Animations */
.animate-pulse-slow {
  animation: pulse 4s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

.animate-bounce-slow {
  animation: bounce 2s infinite;
}

.animate-fade-in-right {
  animation: fadeInRight 1s ease-out forwards;
  opacity: 0;
}

.animate-fade-in-up {
  animation: fadeInUp 0.8s ease-out forwards;
  opacity: 0;
}

.animate-fade-in-down {
  animation: fadeInDown 0.8s ease-out forwards;
  opacity: 0;
}

@keyframes fadeInRight {
  to { opacity: 1; transform: translateX(0); }
  from { opacity: 0; transform: translateX(50px); }
}

@keyframes fadeInUp {
  to { opacity: 1; transform: translateY(0); }
  from { opacity: 0; transform: translateY(30px); }
}

@keyframes fadeInDown {
  to { opacity: 1; transform: translateY(0); }
  from { opacity: 0; transform: translateY(-30px); }
}

/* Animation Delays */
.animation-delay-100 { animation-delay: 0.1s; }
.animation-delay-200 { animation-delay: 0.2s; }
.animation-delay-400 { animation-delay: 0.4s; }
.animation-delay-600 { animation-delay: 0.6s; }
.animation-delay-800 { animation-delay: 0.8s; }
.animation-delay-1000 { animation-delay: 1s; }
</style>
