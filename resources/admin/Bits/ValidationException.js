const ValidationException = (message, errors = []) => {
    const error = new Error(message);
    error.code = 422;
    error.errors = errors;
    return error;
}

ValidationException.prototype = Object.create(Error.prototype);

export default ValidationException;
