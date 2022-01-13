function objToForm(data, formData) {
    for(let pos in data) {
        formData.append(pos, data[pos]);
    }
    return formData;
}

function objToGetParams(data) {
    return Object.keys(data).map(key => key + '=' + data[key]).join('&');
}


function showPreloader() {
    $('#preloader').css('display', 'inherit');
    $('.tab-content').css('display', 'none');
}

function hidePreloader() {
    $('#preloader').css('display', 'none');
    $('.tab-content').css('display', 'block');
}