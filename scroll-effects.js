(function () {
  // 1. 自動注入 AOS 的 CSS
  const link = document.createElement('link');
  link.rel = 'stylesheet';
  link.href = 'https://unpkg.com/aos@next/dist/aos.css';
  document.head.appendChild(link);

  // 2. 動態載入 AOS 的 JS
  const script = document.createElement('script');
  script.src = 'https://unpkg.com/aos@next/dist/aos.js';
  script.onload = () => {
    // 3. 套件載入完成後自動初始化
    AOS.init({
      duration: 800,  // 動畫時間 0.8 秒
      once: true,      // 只觸發一次（不會往回滾又重新跳）
      offset: 100      // 距離視窗底部 100px 時觸發
    });
  };
  document.body.appendChild(script);
})();