//GLOBALS

//LISTENERS
let register_listeners = (callbackfn, thisArg) => {
    let nav_draw = document.querySelector("body > div.top-bar > div > button:nth-child(1)");
    let accordions = document.querySelectorAll(".accordion__section-button");

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
    
    return true;
}

//UI
let main_nav = () =>{
    let nav = document.querySelector(".main-nav");
    if(nav.classList.contains('open')){
        nav.classList.remove('open');
    }else{
        nav.classList.add('open');
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

//API CALLS

//LOAD
register_listeners();