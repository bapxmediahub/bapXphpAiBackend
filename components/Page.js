/**
 * Page layout wrapper: header + content + footer + bottom nav
 */
function Page(content) {
    const wrap = document.createElement('div');
    wrap.appendChild(Header());
    const main = document.createElement('main');
    main.appendChild(content);
    wrap.appendChild(main);
    wrap.appendChild(Footer());
    wrap.appendChild(BottomNav());
    return wrap;
}
