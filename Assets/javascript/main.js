//GLOBALS

//LISTENERS
let register_listeners = (callbackfn, thisArg) => {
    let nav_draw = document.querySelector("body > div.top-bar > div > button:nth-child(1)");
    let accordions = document.querySelectorAll(".accordion__section-button");
    let post_tabs = document.querySelectorAll(".post_tabs button");
    let filters = document.querySelectorAll("form.filters select");

    if(nav_draw) {
        nav_draw.addEventListener("click", () => {
            main_nav();
        });
    }

    if(accordions.length > 0) {
        accordions.forEach((item, index) => {
            item.addEventListener("click", (e) => {
                accordion_draw(e);
            });
        })
    }

    if(post_tabs.length > 0) {
        post_tabs.forEach((item, index) => {
            item.addEventListener("click", (e) => {
                post_tab(item);
            });
        })
    }
    
    if(filters.length > 0) {
        filters.forEach((item, index) => {
            item.addEventListener("change", (e) => {
                item.form.submit();
            });
        })
    }

    return true;
}

//UI
let main_nav = () =>{
    let nav = document.querySelector(".main-nav");
    let nav_draw = document.querySelector("body > div.top-bar > div > button:nth-child(1)");
    if(nav.classList.contains('open')){
        nav.classList.remove('open');
        nav_draw.setAttribute('aria-expanded', 'false');
    }else{
        nav.classList.add('open');
        nav_draw.setAttribute('aria-expanded', 'true');
    }
}

let accordion_draw = (e) =>{
    let accordion = e.srcElement.parentElement.parentElement.parentElement;
    let content = accordion.querySelector(".accordion__section-content");
    if(content.classList.contains('open')){
        content.classList.remove('open');
    }else{
        content.classList.add('open');
    }
}

let post_tab = (tab) =>{
    let post = tab.closest(".post");
    post.querySelectorAll(".post_tabs button").forEach((item) => {
        item.classList.remove('active');
    });
    tab.classList.add('active');
    post.querySelectorAll(".frame").forEach((frame) => {
        if(frame.classList.contains(tab.dataset.frame)){
            frame.classList.remove('hidden');
        }else{
            frame.classList.add('hidden');
        }
    });
}

//API CALLS

//LOAD
register_listeners();