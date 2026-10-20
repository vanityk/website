// js/weather.js
(function () {
  const API_ENDPOINT = 'api/weather.php';

  document.addEventListener('DOMContentLoaded', () => {
    const mountPoint = document.getElementById('weather-app');
    if (!mountPoint) return;

    // 1. 自動注入骨架 HTML
    mountPoint.innerHTML = `
      <div class="weather-card-container">
        <div class="weather-header">
          <h3 class="weather-title">📍 六腳鄉未來一週天氣預報</h3>
          <span class="weather-hint">點選任一日看詳細預報</span>
        </div>
        <table class="weather-table">
          <thead>
            <tr>
              <th>日期</th>
              <th>天氣狀況</th>
              <th>氣溫區間</th>
              <th>降雨機率</th>
            </tr>
          </thead>
          <tbody id="forecast-body">
            <tr><td colspan="4">資料載入中...</td></tr>
          </tbody>
        </table>
      </div>
    `;

    const tableBody = document.getElementById('forecast-body');

    // 2. 抓取資料
    fetch(API_ENDPOINT)
      .then(res => res.json())
      .then(data => {
        if (data.status === 'success' && data.forecast) {
          renderForecast(tableBody, data.forecast);
        } else {
          tableBody.innerHTML = `<tr><td colspan="4">暫無氣象資料</td></tr>`;
        }
      })
      .catch(err => {
        console.error('Weather load error:', err);
        tableBody.innerHTML = `<tr><td colspan="4">資料載入失敗</td></tr>`;
      });
  });

  // 3. 渲染每一天與展開抽屜
  function renderForecast(tbody, list) {
    tbody.innerHTML = '';

    list.forEach((day, index) => {
      const rowId = `row-${index}`;
      const drawerId = `drawer-${index}`;

      // 基本列
      const tr = document.createElement('tr');
      tr.className = 'weather-row';
      tr.id = rowId;
      tr.innerHTML = `
        <td>${day.date}</td>
        <td>${day.desc}</td>
        <td>${day.min_t}°C ~ ${day.max_t}°C</td>
        <td class="rain-tag">💧 ${day.rain}</td>
      `;

      // 展開抽屜
      const detailTr = document.createElement('tr');
      detailTr.className = 'detail-row';
      detailTr.innerHTML = `
        <td colspan="4">
          <div class="detail-drawer" id="${drawerId}">
            <div class="detail-grid">
              <div class="detail-item">
                <span class="detail-label">紫外線指數</span>
                <span class="detail-val">☀️ ${day.uvi || '一般'}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">體感舒適度</span>
                <span class="detail-val">🧘 ${day.comfort || '舒適'}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">風速</span>
                <span class="detail-val">🍃 ${day.wind || '微風'}</span>
              </div>
              <div class="detail-item">
                <span class="detail-label">預估降雨率</span>
                <span class="detail-val rain-tag">💧 ${day.rain}</span>
              </div>
            </div>
            <div class="detail-footer">
              <p class="detail-desc">${day.full_desc || '當天無特殊天氣警示。'}</p>
              <button class="btn-close-detail" type="button">收起 ▲</button>
            </div>
          </div>
        </td>
      `;

      // 互動事件
      tr.addEventListener('click', () => toggleDrawer(index));
      detailTr.querySelector('.btn-close-detail').addEventListener('click', (e) => {
        e.stopPropagation();
        closeAllDrawers();
      });

      tbody.appendChild(tr);
      tbody.appendChild(detailTr);
    });
  }

  function toggleDrawer(index) {
    const drawer = document.getElementById(`drawer-${index}`);
    const row = document.getElementById(`row-${index}`);
    const isOpen = drawer.classList.contains('open');

    closeAllDrawers();

    if (!isOpen) {
      drawer.classList.add('open');
      row.classList.add('active');
    }
  }

  function closeAllDrawers() {
    document.querySelectorAll('.detail-drawer').forEach(d => d.classList.remove('open'));
    document.querySelectorAll('.weather-row').forEach(r => r.classList.remove('active'));
  }
})();