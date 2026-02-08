const request = function (method, route, data = {}) {
    const url = `${window.FluentMailAdmin.rest.url}/${route}`;

    const headers = {'X-WP-Nonce': window.FluentMailAdmin.rest.nonce};

    if (['PUT', 'PATCH', 'DELETE'].indexOf(method.toUpperCase()) !== -1) {
        headers['X-HTTP-Method-Override'] = method;
        method = 'POST';
    }

    data.query_timestamp = Date.now();

    return new Promise((resolve, reject) => {
        window.jQuery.ajax({
            url: url,
            type: method,
            data: data,
            headers: headers
        })
            .then(response => resolve(response))
            .fail(errors => reject(errors.responseJSON || errors.responseText));
    });
}

export default {
    get(route, data = {}) {
        return request('GET', route, data);
    },
    post(route, data = {}) {
        return request('POST', route, data);
    },
    delete(route, data = {}) {
        return request('DELETE', route, data);
    },
    put(route, data = {}) {
        return request('PUT', route, data);
    },
    patch(route, data = {}) {
        return request('PATCH', route, data);
    }
};

jQuery(document).ajaxSuccess((event, xhr, settings) => {
    const nonce = xhr.getResponseHeader('X-WP-Nonce');
    if (nonce) {
        window.FluentMailAdmin.rest_nonce = nonce;
    }
});
