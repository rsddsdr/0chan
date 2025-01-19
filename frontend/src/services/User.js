import Api from './Api'
import Session from './Session'
import BusEvents from '../app/BusEvents'

export default {
    login(login, password) {
        return Api.post('user/login', { login, password })
            .then(response => {
                if (response.data.ok) {
                    Session.get();
                    BusEvents.$bus.emit(BusEvents.REFRESH_SIDEBAR);
                }

                return response;
            });
    },
    logout() {
        return Api.get('user/logout')
            .then(response => {
                if (response.data.ok) {
                    Session.get();
                    BusEvents.$bus.emit(BusEvents.REFRESH_SIDEBAR);
                }
                return response;
            });
    },
    register(login, password, email, invite) {
        return Api.post('user/register', { login, password, invite }).then(
            response => {
                if (response.data.ok) {
                    Session.get();
                    BusEvents.$bus.emit(BusEvents.REFRESH_SIDEBAR);
                }
                return response;
            }
        );
    },
    changePassword(oldPassword, newPassword) {
        return Api.post('user/changePassword', { oldPassword, newPassword });
    },
    get() {
        return Api.get('user');
    },
    save(settings) {
        return Api.post('user', settings);
    },
    set_style(custom_css) {
        return Api.post('user/setStyle', { custom_css });
    },
    list() {
        return Api.post('user/list');
    },
    remove(user_id) {
        return Api.get('user/remove', { params: { user: user_id } })
    }
}