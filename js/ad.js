(function () {
  // 預設加上 display: none; 讓彈窗一開始處於隱藏狀態
  const adHTML = `
  <div id="simple-ad" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.45); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); justify-content: center; align-items: center; z-index: 9999; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
    <div style="position: relative; width: 90%; max-width: 420px; background: #ffffff; border-radius: 16px; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2); overflow: hidden; text-align: center; border: 1px solid rgba(255, 255, 255, 0.8);">
      
      <!-- 右上角關閉叉叉 -->
      <button id="ad-close-cross" style="position: absolute; top: 12px; right: 12px; width: 32px; height: 32px; border-radius: 50%; border: none; background: rgba(0, 0, 0, 0.06); color: #666; font-size: 18px; line-height: 1; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 2;" title="關閉">&times;</button>

      <!-- 宣傳圖 -->
      <img src="pic_assest/ad/ncyu_logo.png" alt="Special Offer" style="width: 100%; height: 120px; object-fit: cover; display: block;">

      <!-- 內容區域 -->
      <div style="padding: 24px 28px 28px;">
        <span style="display: inline-block; padding: 4px 10px; background: #fef2f2; color: #ef4444; font-size: 12px; font-weight: 600; border-radius: 20px; margin-bottom: 12px; letter-spacing: 0.5px;">LIMITED OFFER</span>
        <h3 style="margin: 0 0 10px; font-size: 22px; font-weight: 700; color: #111827; letter-spacing: -0.5px;">會員專屬內容！</h3>
        <p style="margin: 0 0 20px; font-size: 14px; color: #6b7280; line-height: 1.6;">現在立即加入嘉義大學資管系就享8折優惠！</p>

        <!-- 按鈕群組容器 -->
        <div id="btn-container" style="position: relative; height: 110px; display: flex; flex-direction: column; align-items: center; justify-content: flex-start;">
          <!-- 立即探索按鈕 -->
          <a id="cta-btn" href="https://misweb.ncyu.edu.tw/" style="display: block; width: 100%; padding: 12px 0; background: #111827; color: #ffffff; text-decoration: none; border-radius: 8px; font-size: 14px; font-weight: 600; box-sizing: border-box; letter-spacing: 0.5px; transition: transform 0.25s ease, background 0.3s;">立即探索專屬禮遇</a>
          
          <!-- 稍後再說按鈕 -->
          <button id="later-btn" style="position: absolute; bottom: 8px; background: none; border: none; color: #9ca3af; font-size: 13px; cursor: pointer; text-decoration: underline; padding: 8px 16px; transition: transform 0.25s ease;">稍後再說</button>
        </div>
      </div>
    </div>
  </div>
  `;

  // 將彈窗掛載到頁面上
  document.body.insertAdjacentHTML('beforeend', adHTML);

  const adPopup = document.getElementById('simple-ad');
  const ctaBtn = document.getElementById('cta-btn');
  const laterBtn = document.getElementById('later-btn');
  const closeCross = document.getElementById('ad-close-cross');

  let isSwapped = false;

  // 滑鼠移到「稍後再說」時交換位置
  laterBtn.addEventListener('mouseenter', () => {
    if (!isSwapped) {
      laterBtn.style.transform = 'translateY(-58px)';
      ctaBtn.style.transform = 'translateY(52px)';
    } else {
      laterBtn.style.transform = 'translateY(0)';
      ctaBtn.style.transform = 'translateY(0)';
    }
    isSwapped = !isSwapped;
  });

  // 點擊「立即探索」：新分頁開啟、2秒後自動關閉
  ctaBtn.addEventListener('click', (e) => {
    e.preventDefault();
    window.open(ctaBtn.href, '_blank');

    ctaBtn.innerText = '正在為您跳轉...';
    ctaBtn.style.background = '#059669';

    setTimeout(() => {
      adPopup.style.display = 'none';
      ctaBtn.innerText = '立即探索專屬禮遇';
      ctaBtn.style.background = '#111827';
      ctaBtn.style.transform = 'translateY(0)';
      laterBtn.style.transform = 'translateY(0)';
      isSwapped = false;
    }, 2000);
  });

  // 右上角叉叉直接關閉
  closeCross.addEventListener('click', () => {
    adPopup.style.display = 'none';
  });

  // 延遲 1 秒（1000 毫秒）後淡入彈窗
  setTimeout(() => {
    adPopup.style.display = 'flex';
  }, 1000);
})();