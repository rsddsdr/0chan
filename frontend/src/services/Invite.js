import Api from './Api'

export default {
	get() {
		return Api.get('invite');
	},
	create() {
		return Api.get('invite/create');
	}
}