/** Registration validation UX only; Laravel remains the authority. */
export const registrationPasswordMismatch = (password, confirmation) =>
    Boolean(password && confirmation && password !== confirmation);

export const registrationPasswordError = (password, confirmation) =>
    registrationPasswordMismatch(password, confirmation)
        ? 'Password dan konfirmasi password harus sama.'
        : null;
