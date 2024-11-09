//GLOBALS

//LISTENERS
let register_listeners = () => {
    document.querySelector("body > div.top-bar > div > button:nth-child(1)").addEventListener("click", ()=>{
        main_nav();
    });
    
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

//API CALLS

//LOAD
register_listeners();