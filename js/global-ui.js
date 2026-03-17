document.addEventListener('DOMContentLoaded', function () {
  try {
    // Skip back button on login and registration pages
    const currentPage = window.location.pathname;
    if (currentPage.includes('applicantlogin.php') || 
        currentPage.includes('login.php') ||
        currentPage.includes('register.php')) {
      return;
    }

    // If the page already provides a Back button/link, do nothing.
    const existingBack = Array.from(document.querySelectorAll('a,button')).some((el) => {
      const t = (el.textContent || '').trim().toLowerCase();
      if (!t) return false;
      if (t === 'back' || t.startsWith('back ')) return true;
      const cls = (el.className || '').toString().toLowerCase();
      if (cls.includes('btn-back') || cls.includes('back')) return true;
      if (el.getAttribute('data-back-button') === '1') return true;
      return false;
    });

    if (existingBack) return;

    // Only show when there is actually somewhere to go back to.
    if (window.history.length <= 1) return;

    const container =
      document.querySelector('.main-content') ||
      document.querySelector('.container') ||
      document.querySelector('body');

    const wrap = document.createElement('div');
    wrap.style.marginBottom = '12px';

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = 'Back';
    btn.setAttribute('data-back-button', '1');

    // Keep styling minimal and aligned with existing pages.
    btn.style.background = '#004225';
    btn.style.color = '#fff';
    btn.style.border = '1px solid #006400';
    btn.style.background = '#006400';
    btn.style.padding = '6px 10px';
    btn.style.borderRadius = '4px';
    btn.style.cursor = 'pointer';

    btn.addEventListener('click', function () {
      try {
        window.history.back();
      } catch (e) {
        // no-op
      }
    });

    wrap.appendChild(btn);

    if (container && container.firstChild) {
      container.insertBefore(wrap, container.firstChild);
    } else if (container) {
      container.appendChild(wrap);
    }
  } catch (err) {
    // Fail silently
  }
});

// Ensure users don't see stale data when navigating Back/Forward.
// This avoids the common "I need to refresh" symptom caused by BFCache.
window.addEventListener('pageshow', function (event) {
  try {
    if (event && event.persisted) {
      window.location.reload();
    }
  } catch (err) {
    // Fail silently
  }
});

// Sidebar collapse behavior for CS Admin: when a sidebar link is clicked we
// collapse the sidebar on the *next* page so the dashboard appears hidden
// (matches applicants UX where you use Back to return to the dashboard).
document.addEventListener('DOMContentLoaded', function () {
  try {
    const sidebar = document.querySelector('.sidebar');
    const main = document.querySelector('.main-content');
    if (!sidebar) return;

    // When a sidebar link is clicked, set a session flag so the next page
    // load will hide the sidebar until the user navigates back.
    Array.from(sidebar.querySelectorAll('a')).forEach(function (a) {
      a.addEventListener('click', function (ev) {
        try {
          const href = a.getAttribute('href');
          if (!href || href.startsWith('#') || a.target === '_blank') return;
          sessionStorage.setItem('csSidebarCollapsed', '1');
        } catch (e) {}
        // allow normal navigation to proceed
      });
    });

    // If the flag is present, collapse the sidebar on load. We intentionally
    // do NOT inject a floating 'Menu' button to keep the UI tidy.
    if (sessionStorage.getItem('csSidebarCollapsed') === '1') {
      sidebar.classList.add('collapsed');
      if (main) main.classList.add('sidebar-collapsed');
    }

    // Our injected Back button clears the flag before navigating back so the
    // dashboard reappears when the user returns.
    const backBtn = document.querySelector('[data-back-button="1"]');
    if (backBtn) {
      backBtn.addEventListener('click', function (e) {
        try { sessionStorage.removeItem('csSidebarCollapsed'); } catch (er) {}
        // allow history.back to run (the handler in global-ui already calls it)
      });
    }

    // Clear the flag on pageshow for bfcache navigations (so Back restores state)
    window.addEventListener('pageshow', function (evt) {
      try {
        if (evt && evt.persisted) sessionStorage.removeItem('csSidebarCollapsed');
      } catch (e) {}
    });
  } catch (err) {
    // fail silently
  }
});
