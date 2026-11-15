// Настройки фронта. По умолчанию API — папка api рядом с этим файлом, поэтому сайт работает
// и в корне домена, и в подпапке (example.ru/handmade/). Если API на другом домене — укажите полный адрес:
// apiBase: 'https://api.example.ru/api'
window.SITE_CONFIG = {
  apiBase: new URL('api', document.currentScript.src).pathname,

  admin: {
    // simple — упрощённая админка для заказчика: технические настройки скрыты (не удалены).
    // full — всё сразу. Разработчик может включить полный режим в своём браузере ссылкой /admin?full=1
    // (и выключить — /admin?full=0), не меняя этот файл.
    mode: 'simple',

    // Контакты разработчика — показываются в блоках «Хотите расширить функционал?»
    developer: {
      name: 'разработчиком',
      telegram: '', // https://t.me/username
      phone: '', // +7 999 000-00-00
      email: '',
    },
  },
};
