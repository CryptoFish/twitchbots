var select = document.getElementById('existing-type');
var input = document.getElementById("bottype");
function update() {
    if(parseInt(select.value, 10) == 0)
        input.removeAttribute("hidden");
    else
        input.setAttribute("hidden", true);
}
select.addEventListener("change", update);
update();
