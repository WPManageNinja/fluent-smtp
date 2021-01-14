/**
 * Handles the Error response.
 */
class Errors {
    constructor() {
        this.errors = {}
    }

    get(field) {
        if (this.errors[field]) {
            return Object.values(this.errors[field])[0];
        }
    }

    has(field) {
        return !!this.errors[field]
    }

    record(errors) {
        this.errors = errors
    }

    clear() {
        this.errors = {}
    }
}

export default Errors;
