<template>
  <div>
    <!-- Hero Section -->
    <section class="relative bg-gradient-to-br from-primary-50 via-white to-accent-50 dark:from-neutral-900 dark:via-neutral-800 dark:to-neutral-900 overflow-hidden">
      <div class="absolute inset-0 opacity-10">
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-primary-500 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-accent-500 rounded-full blur-3xl"></div>
      </div>

      <div class="container-custom relative py-20">
        <div class="max-w-4xl mx-auto text-center">
          <h1 class="text-4xl md:text-5xl lg:text-6xl font-display font-bold mb-6 animate-fade-in-up">
            About <span class="text-gradient">Me</span>
          </h1>
          <p class="text-xl text-neutral-600 dark:text-neutral-300 animate-fade-in-up animate-delay-100">
            Passionate developer crafting digital experiences
          </p>
        </div>
      </div>
    </section>

    <!-- Introduction Section -->
    <section class="section bg-white dark:bg-neutral-800">
      <div class="container-custom">
        <div class="max-w-4xl mx-auto">
          <div>&nbsp;</div>
          <div class="grid md:grid-cols-2 gap-12 items-center">
            <div class="aspect-square bg-neutral-200 dark:bg-neutral-700 rounded-2xl"></div>
            <div>
              <h2 class="text-3xl md:text-4xl font-display font-bold mb-6">Hi, I'm [Your Name]</h2>
              <p class="text-neutral-600 dark:text-neutral-400 mb-4">
                I'm a full-stack developer with over 5 years of experience building web applications and digital solutions.
                I specialize in modern JavaScript frameworks, cloud architecture, and creating seamless user experiences.
              </p>
              <p class="text-neutral-600 dark:text-neutral-400 mb-6">
                My passion lies in solving complex problems with elegant solutions and continuously learning new technologies
                to stay at the forefront of web development.
              </p>
              <BaseButton variant="primary" @click="$router.push('/contact')">
                Get in Touch
              </BaseButton>
            </div>
          </div>
        </div>
      </div>
      <div>&nbsp;</div>
    </section>
    <!-- Skills Section -->
    <section class="section bg-neutral-50 dark:bg-neutral-900">
      <div>&nbsp;</div>
      <div class="container-custom">
        <div class="text-center mb-12">
          <h2 class="text-3xl md:text-4xl font-display font-bold mb-4">Skills & Expertise</h2>
          <p class="text-lg text-neutral-600 dark:text-neutral-400 max-w-2xl mx-auto">
            Technologies and tools I work with
          </p>
        </div>

        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 max-w-5xl mx-auto">
          <BaseCard v-for="skill in skills" :key="skill.category">
            <h3 class="text-xl font-semibold mb-4">{{ skill.category }}</h3>
            <div class="flex flex-wrap gap-2">
              <BaseBadge v-for="tech in skill.technologies" :key="tech" variant="primary" size="sm">
                {{ tech }}
              </BaseBadge>
            </div>
          </BaseCard>
        </div>
      </div>
    </section>

    <div>&nbsp;</div>

    <!-- Experience Section -->
    <section class="section bg-white dark:bg-neutral-800">
      <div class="container-custom">
        <div class="text-center mb-12">
          <h2 class="text-3xl md:text-4xl font-display font-bold mb-4">Experience</h2>
          <p class="text-lg text-neutral-600 dark:text-neutral-400 max-w-2xl mx-auto">
            My professional journey
          </p>
        </div>

        <div class="max-w-3xl mx-auto space-y-8">
          <div v-for="exp in experiences" :key="exp.company" class="border-l-4 border-primary-500 pl-6 pb-8">
            <div class="flex items-center gap-2 mb-2">
              <h3 class="text-xl font-semibold">{{ exp.position }}</h3>
              <BaseBadge variant="info" size="sm">{{ exp.period }}</BaseBadge>
            </div>
            <p class="text-neutral-600 dark:text-neutral-400 mb-2">{{ exp.company }}</p>
            <p class="text-neutral-600 dark:text-neutral-400 text-sm">{{ exp.description }}</p>
          </div>
        </div>
      </div>
    </section>
    <div>&nbsp;</div> 

    <!-- CTA Section -->
    <section class="section bg-gradient-to-r from-primary-600 to-accent-600 text-white">
      <div>&nbsp;</div>
      <div class="container-custom text-center">
        <h2 class="text-3xl md:text-4xl font-display font-bold mb-4">Let's Work Together</h2>
        <p class="text-lg md:text-xl mb-8 opacity-90 max-w-2xl mx-auto">
          Interested in collaborating? I'd love to hear about your project.
        </p>
        <BaseButton variant="secondary" size="lg" @click="$router.push('/contact')">
          Contact Me
        </BaseButton>
      </div>
      <div>&nbsp;</div>
    </section>
  </div>
</template>

<script setup>
import { BaseButton, BaseCard, BaseBadge } from '@/components/base'

const skills = [
  {
    category: 'Frontend',
    technologies: ['Vue.js', 'React', 'TypeScript', 'Tailwind CSS', 'HTML5', 'CSS3']
  },
  {
    category: 'Backend',
    technologies: ['Node.js', 'Express', 'Laravel', 'PHP', 'MySQL', 'MongoDB']
  },
  {
    category: 'DevOps & Tools',
    technologies: ['Git', 'Docker', 'AWS', 'CI/CD', 'Linux', 'Nginx']
  }
]

const experiences = [
  {
    position: 'Senior Full Stack Developer',
    company: 'Tech Company Inc.',
    period: '2021 - Present',
    description: 'Leading development of enterprise web applications using Vue.js, Node.js, and cloud technologies.'
  },
  {
    position: 'Full Stack Developer',
    company: 'Digital Agency',
    period: '2019 - 2021',
    description: 'Developed and maintained multiple client projects using modern web technologies and agile methodologies.'
  },
  {
    position: 'Frontend Developer',
    company: 'Startup Co.',
    period: '2017 - 2019',
    description: 'Built responsive user interfaces and implemented design systems for SaaS applications.'
  }
]
</script>
