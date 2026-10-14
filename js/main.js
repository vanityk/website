
  function toggleDropdown(event) {
    // 阻止點擊事件往外傳遞，避免馬上被 window click 關掉
    event.stopPropagation();
    const dropdown = document.getElementById('navDropdown');
    dropdown.classList.toggle('show');
  }

  // 點擊網頁任一空白處，選單會自動收合
  window.addEventListener('click', function () {
    const dropdown = document.getElementById('navDropdown');
    if (dropdown && dropdown.classList.contains('show')) {
      dropdown.classList.remove('show');
    }
  });