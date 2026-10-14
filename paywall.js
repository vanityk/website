(function () {
  // 1. 動態注入彈窗專屬 CSS 樣式
  const style = document.createElement('style');
  style.textContent = `
    .paywall-overlay {
      position: fixed;
      inset: 0;
      width: 100vw;
      height: 100vh;
      background: rgba(44, 38, 35, 0.65);
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.3s ease, visibility 0.3s ease;
      z-index: 9999;
      padding: 20px;
      box-sizing: border-box;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans TC", sans-serif;
    }

    .paywall-overlay.active {
      opacity: 1;
      visibility: visible;
    }

    .paywall-card {
      position: relative;
      background-color: #FAF8F5;
      border: 1px solid rgba(92, 61, 46, 0.15);
      border-radius: 20px;
      max-width: 920px;
      width: 100%;
      padding: 44px 36px;
      box-shadow: 0 24px 60px rgba(0, 0, 0, 0.25);
      transform: scale(0.92);
      transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      box-sizing: border-box;
    }

    .paywall-overlay.active .paywall-card {
      transform: scale(1);
    }

    .paywall-close {
      position: absolute;
      top: 16px;
      right: 20px;
      background: none;
      border: none;
      font-size: 30px;
      color: #7A7067;
      cursor: pointer;
      line-height: 1;
      transition: color 0.2s;
    }

    .paywall-close:hover {
      color: #332C27;
    }

    .paywall-header {
      text-align: center;
      margin-bottom: 32px;
    }

    .paywall-badge {
      font-size: 12px;
      letter-spacing: 2px;
      color: #C89B3C;
      font-weight: 700;
      display: inline-block;
      margin-bottom: 6px;
    }

    .paywall-header h2 {
      font-size: 26px;
      color: #5C3D2E;
      margin: 0 0 8px 0;
      font-weight: 700;
    }

    .paywall-header p {
      color: #7A7067;
      font-size: 15px;
      max-width: 480px;
      margin: 0 auto;
      line-height: 1.5;
    }

    /* 方案網格容器 */
    .pricing-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
      gap: 20px;
      align-items: stretch;
      padding-top: 14px; /* 預留浮起空間 */
    }

    /* 單一方案卡片基礎樣式 */
    .pricing-plan {
      background: #FFFFFF;
      border: 2px solid transparent;
      outline: 1px solid rgba(0, 0, 0, 0.08);
      border-radius: 16px;
      padding: 26px 20px;
      display: flex;
      flex-direction: column;
      position: relative;
      text-align: center;
      cursor: pointer;
      transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), 
                  box-shadow 0.25s ease, 
                  border-color 0.25s ease, 
                  background-color 0.25s ease;
      user-select: none;
    }

    /* 【關鍵 1：Hover 懸停效果】微浮起 + 邊框微亮 */
    .pricing-plan:hover {
      transform: translateY(-8px);
      box-shadow: 0 16px 32px rgba(92, 61, 46, 0.12);
      border-color: rgba(92, 61, 46, 0.4);
    }

    /* 【關鍵 2：Selected 選中效果】維持高亮深色主視覺 */
    .pricing-plan.selected {
      transform: translateY(-8px);
      border-color: #5C3D2E;
      outline: none;
      background: #FFFDF9;
      box-shadow: 0 16px 36px rgba(92, 61, 46, 0.18);
    }

    /* 選中或懸停時的標籤徽章 */
    .plan-tag {
      position: absolute;
      top: -12px;
      left: 50%;
      transform: translateX(-50%);
      background-color: #5C3D2E;
      color: #FFFFFF;
      font-size: 11px;
      font-weight: 600;
      padding: 2px 14px;
      border-radius: 12px;
      letter-spacing: 1px;
      opacity: 0;
      transition: opacity 0.2s ease;
    }

    .pricing-plan.selected .plan-tag,
    .pricing-plan:hover .plan-tag {
      opacity: 1;
    }

    .plan-name {
      font-size: 17px;
      font-weight: 700;
      color: #332C27;
      margin-bottom: 10px;
    }

    .plan-price {
      font-size: 26px;
      font-weight: 700;
      color: #5C3D2E;
      margin-bottom: 16px;
    }

    .plan-price span {
      font-size: 13px;
      color: #7A7067;
      font-weight: 400;
    }

    .plan-features {
      list-style: none;
      padding: 0;
      margin: 0 0 24px 0;
      text-align: left;
      flex-grow: 1;
    }

    .plan-features li {
      font-size: 13px;
      color: #555;
      margin-bottom: 8px;
      line-height: 1.4;
    }

    /* 按鈕狀態聯動 */
    .plan-btn {
      width: 100%;
      padding: 10px 0;
      border-radius: 8px;
      border: 1px solid #5C3D2E;
      background: transparent;
      color: #5C3D2E;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.2s;
    }

    /* 選中或懸停時按鈕反白變實心 */
    .pricing-plan:hover .plan-btn,
    .pricing-plan.selected .plan-btn {
      background: #5C3D2E;
      color: #FFFFFF;
    }
  `;
  document.head.appendChild(style);

  // 2. 動態生成彈窗 HTML 結構
  const modalHTML = `
    <div id="paywall-overlay" class="paywall-overlay" onclick="closePaywallModal(event)">
      <div class="paywall-card" onclick="event.stopPropagation()">
        <button class="paywall-close" onclick="closePaywallModal()">&times;</button>
        <div class="paywall-header">
          <span class="paywall-badge">PREMIUM ACCESS</span>
          <h2>解鎖深度六腳文旅指南</h2>
          <p>點擊方案即可選取，加入會員探索完整私房景點地圖與文史全紀錄。</p>
        </div>
        <div class="pricing-grid">
          <!-- 方案 1 -->
          <div class="pricing-plan" onclick="selectPlan(this)">
            <div class="plan-tag">已選擇</div>
            <div class="plan-name">單月漫遊</div>
            <div class="plan-price">NT$ 99 <span>/ 月</span></div>
            <ul class="plan-features">
              <li>✔ 當季景點專題</li>
              <li>✔ 景點音訊導覽</li>
              <li>✖ 離線地圖下載</li>
            </ul>
            <button type="button" class="plan-btn">選擇方案</button>
          </div>

          <!-- 方案 2（預設選中） -->
          <div class="pricing-plan selected" onclick="selectPlan(this)">
            <div class="plan-tag">最受歡迎</div>
            <div class="plan-name">年度深度</div>
            <div class="plan-price">NT$ 899 <span>/ 年</span></div>
            <ul class="plan-features">
              <li>✔ 全站深度導覽無限看</li>
              <li>✔ 高清離線導覽地圖包</li>
              <li>✔ 在地店家專屬優惠折扣</li>
            </ul>
            <button type="button" class="plan-btn">已選取此方案</button>
          </div>

          <!-- 方案 3 -->
          <div class="pricing-plan" onclick="selectPlan(this)">
            <div class="plan-tag">永久典藏</div>
            <div class="plan-name">文史贊助</div>
            <div class="plan-price">NT$ 1,999 <span>/ 永久</span></div>
            <ul class="plan-features">
              <li>✔ 永久解鎖全站數位典藏</li>
              <li>✔ 贊助者名冊列名感謝</li>
              <li>✔ 實體文創明信片套組</li>
            </ul>
            <button type="button" class="plan-btn">選擇方案</button>
          </div>
        </div>
      </div>
    </div>
  `;

  // 掛載到 DOM
  const mountModal = () => {
    const div = document.createElement('div');
    div.innerHTML = modalHTML;
    document.body.appendChild(div.firstElementChild);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountModal);
  } else {
    mountModal();
  }
})();

// 3. 點擊選中邏輯：單選模式切換
window.selectPlan = function (cardElement) {
  // 移除所有方案的選中狀態
  document.querySelectorAll('.pricing-plan').forEach(card => {
    card.classList.remove('selected');
    const btn = card.querySelector('.plan-btn');
    if (btn) btn.textContent = '選擇方案';
  });

  // 為當前點擊的卡片加上選中狀態
  cardElement.classList.add('selected');
  const activeBtn = cardElement.querySelector('.plan-btn');
  if (activeBtn) activeBtn.textContent = '已選取此方案';
};

// 4. 控制彈窗開關
window.openPaywallModal = function () {
  const overlay = document.getElementById('paywall-overlay');
  if (overlay) {
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }
};

window.closePaywallModal = function () {
  const overlay = document.getElementById('paywall-overlay');
  if (overlay) {
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  }
};