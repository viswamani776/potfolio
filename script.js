/* ========================================================================= */
/* THIRUMALAI VISWA B — PORTFOLIO SCRIPT                                     */
/* ========================================================================= */

// 1. Force Scroll to Hero Section on Refresh / Initial Page Load
if ('scrollRestoration' in history) {
  history.scrollRestoration = 'manual';
}

window.addEventListener('beforeunload', () => {
  window.scrollTo(0, 0);
});

window.scrollTo(0, 0);

function scrollToHero() {
  if (window.location.hash) {
    history.replaceState(null, '', window.location.pathname + window.location.search);
  }
  const hero = document.getElementById('home');
  if (hero) {
    hero.scrollIntoView({ behavior: 'instant', block: 'start' });
  } else {
    window.scrollTo({ top: 0, left: 0, behavior: 'instant' });
  }
}

// 2. Interactive Cursor Spotlight, Ambient Canvas & Form Handling
function initInteractiveEffects() {
  // Always ensure we start at the hero section
  scrollToHero();

  // Mouse Spotlight Tracker
  const cursorGlow = document.getElementById('cursor-glow');
  if (cursorGlow) {
    window.addEventListener('pointermove', (e) => {
      cursorGlow.style.left = e.clientX + 'px';
      cursorGlow.style.top = e.clientY + 'px';
    });
  }

  // Interactive Constellation Canvas
  const canvas = document.getElementById('ambient-canvas');
  if (canvas) {
    const ctx = canvas.getContext('2d');
    let width, height;
    let particles = [];

    function resize() {
      width = canvas.width = window.innerWidth;
      height = canvas.height = window.innerHeight;
    }
    window.addEventListener('resize', resize);
    resize();

    class Particle {
      constructor() {
        this.x = Math.random() * width;
        this.y = Math.random() * height;
        this.vx = (Math.random() - 0.5) * 0.4;
        this.vy = (Math.random() - 0.5) * 0.4;
        this.radius = Math.random() * 1.5 + 0.5;
        this.alpha = Math.random() * 0.4 + 0.1;
      }
      update() {
        this.x += this.vx;
        this.y += this.vy;
        if (this.x < 0) this.x = width;
        if (this.x > width) this.x = 0;
        if (this.y < 0) this.y = height;
        if (this.y > height) this.y = 0;
      }
      draw() {
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
        ctx.fillStyle = `rgba(245, 158, 11, ${this.alpha})`;
        ctx.fill();
      }
    }

    const count = Math.min(width < 768 ? 30 : 60, 70);
    for (let i = 0; i < count; i++) {
      particles.push(new Particle());
    }

    function animate() {
      ctx.clearRect(0, 0, width, height);

      // Connect nodes
      for (let i = 0; i < particles.length; i++) {
        for (let j = i + 1; j < particles.length; j++) {
          const dx = particles[i].x - particles[j].x;
          const dy = particles[i].y - particles[j].y;
          const dist = Math.sqrt(dx * dx + dy * dy);
          if (dist < 110) {
            ctx.beginPath();
            ctx.moveTo(particles[i].x, particles[i].y);
            ctx.lineTo(particles[j].x, particles[j].y);
            ctx.strokeStyle = `rgba(245, 158, 11, ${0.12 * (1 - dist / 110)})`;
            ctx.lineWidth = 0.6;
            ctx.stroke();
          }
        }
      }

      particles.forEach((p) => {
        p.update();
        p.draw();
      });
      requestAnimationFrame(animate);
    }
    animate();
  }

  // Contact Form Submission (Asynchronous to contact.php)
  const contactForm = document.getElementById('contact-form');
  const feedback = document.getElementById('feedback');
  const feedbackError = document.getElementById('feedback-error');
  const submitBtn = document.getElementById('submit-btn');
  const submitBtnText = document.getElementById('submit-btn-text');

  if (contactForm) {
    contactForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      if (feedback) feedback.classList.add('hidden');
      if (feedbackError) {
        feedbackError.classList.add('hidden');
        feedbackError.textContent = '';
      }

      // Button loading state
      if (submitBtn) submitBtn.disabled = true;
      if (submitBtnText) submitBtnText.textContent = 'TRANSMITTING...';

      const formData = new FormData(contactForm);

      try {
        const response = await fetch(contactForm.action || 'contact.php', {
          method: 'POST',
          body: formData,
          headers: {
            'Accept': 'application/json'
          }
        });

        const result = await response.json().catch(() => null);

        if (response.ok && result && result.success) {
          if (feedback) {
            feedback.textContent = result.message || '✓ Transmission received. Thirumalai Viswa will respond promptly.';
            feedback.classList.remove('hidden');
          }
          contactForm.reset();
        } else {
          // Display validation error or general failure
          const errorMsg = (result && result.errors)
            ? Object.values(result.errors).join(' ')
            : (result && result.message) || 'Submission error. Please check your inputs or try again.';
          
          if (feedbackError) {
            feedbackError.textContent = '✕ ' + errorMsg;
            feedbackError.classList.remove('hidden');
          } else {
            alert(errorMsg);
          }
        }
      } catch (err) {
        // Fallback message if network or local PHP server isn't active
        if (feedbackError) {
          feedbackError.textContent = '✕ Unable to transmit message. Please ensure a PHP server is running, or reach out directly via thirumalaiviswa37@gmail.com';
          feedbackError.classList.remove('hidden');
        }
      } finally {
        if (submitBtn) submitBtn.disabled = false;
        if (submitBtnText) submitBtnText.textContent = 'TRANSMIT MESSAGE';
      }
    });
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initInteractiveEffects);
} else {
  initInteractiveEffects();
}

window.addEventListener('load', scrollToHero);
