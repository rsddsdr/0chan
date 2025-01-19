import Api from './Api'

export default {
    list() {
        return Api.get('globals/list');
    },
    moderators(boardDir) {
        return Api.get('management/moderators', { params: { board: boardDir }});
    },
    add(userLogin, isAdmin) {
        if (isAdmin) {
            return Api.get('globals/add', { params: { user: userLogin, isAdmin: !!isAdmin }})
        } else {
            return Api.get('globals/add', { params: { user: userLogin }})
        }
    },
    remove(userLogin) {
        return Api.get('globals/remove', { params: { user: userLogin }})
    },
    settings() {
        return Api.get('globals/settings');
    },
    save(settings) {
        return Api.post('globals/settingsUpdate', settings);
    }
}
