// js/weather.js
(function () {
  const API_ENDPOINT = 'api/weather.php';

  function initWeatherWidget() {
    const mountPoint = document.getElementById('weather-app');
    if (!mountPoint) return;

    mountPoint.innerHTML = `
      <div class="weather-card-container">
        <div class="weather-header">
          <h3 class="weather-title">六腳鄉未來一週天氣預報</h3>
          <span class="weather-hint">點選卡片橫向展開詳細預報</span>
        </div>
        <div class="weather-cards-row" id="weather-cards-row">
          <div style="padding: 20px; width: 100%; text-align: center; opacity: 0.75;">氣象資料讀取中...</div>
        </div>
      </div>
    `;

    const rowContainer = document.getElementById('weather-cards-row');

    fetch(API_ENDPOINT)
      .then(res => {
        if (!res.ok) throw new Error('API 請求失敗');
        return res.json();
      })
      .then(data => {
        if (data.status === 'success' && Array.isArray(data.forecast) && data.forecast.length > 0) {
          renderHorizontalCards(rowContainer, data.forecast);
        } else {
          rowContainer.innerHTML = `<div style="padding: 20px; width: 100%; text-align: center;">暫無氣象預報資料</div>`;
        }
      })
      .catch(err => {
        console.error('Weather load error:', err);
        rowContainer.innerHTML = `<div style="padding: 20px; width: 100%; text-align: center; color: #ff7b72;">連線失敗，請檢查網路或 API。</div>`;
      });
  }

  function renderHorizontalCards(container, list) {
    container.innerHTML = '';

    list.forEach((day, index) => {
      const card = document.createElement('div');
      card.className = 'weather-day-card';
      card.id = `day-card-${index}`;
      const displayDate = (day.date || '--').replace('-', '/');
      card.innerHTML = `
        <!-- 左側：簡易垂直概況 -->
        <div class="card-summary">
          <span class="card-date">${displayDate}</span>
          <span class="card-desc">${day.desc || '多雲'}</span>
          <span class="card-temp">${day.min_t}° ~ ${day.max_t}°</span>
          <span class="rain-tag">💧 ${day.rain || '0%'}</span>
        </div>

        <!-- 右側：橫向展開詳細視圖 -->
        <div class="card-details">
          <div class="detail-items-grid">
            <div class="detail-item-col">
              <span class="detail-item-label">紫外線</span>
              <span class="detail-item-val">☀️ ${day.uvi || '一般'}</span>
            </div>
            <div class="detail-item-col">
              <span class="detail-item-label">舒適度</span>
              <span class="detail-item-val">🧘 ${day.comfort || '舒適'}</span>
            </div>
            <div class="detail-item-col">
              <span class="detail-item-label">風速</span>
              <span class="detail-item-val">🍃 ${day.wind || '微風'}</span>
            </div>
            <div class="detail-item-col">
              <span class="detail-item-label">降雨率</span>
              <span class="detail-item-val rain-tag">💧 ${day.rain || '0%'}</span>
            </div>
          </div>
          <p class="detail-narrative">${day.full_desc || '全日氣候穩定，外出請隨時留意早晚溫差。'}</p>
          <button type="button" class="btn-collapse-card">收起 ◀</button>
        </div>
      `;

      // 點擊卡片本體：切換展開狀態
      card.addEventListener('click', () => {
        if (!card.classList.contains('is-expanded')) {
          // 先把其他已經展開的收合回去
          document.querySelectorAll('.weather-day-card.is-expanded').forEach(c => {
            c.classList.remove('is-expanded');
          });
          card.classList.add('is-expanded');

          // 自動平滑滾動到視野中
          card.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
        }
      });

      // 關鍵修復：收合按鈕
      const collapseBtn = card.querySelector('.btn-collapse-card');
      collapseBtn.addEventListener('click', (e) => {
        e.stopPropagation(); // 阻止事件向上冒泡到 card，避免卡片又被重新打開
        card.classList.remove('is-expanded');
      });

      container.appendChild(card);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initWeatherWidget);
  } else {
    initWeatherWidget();
  }
})();