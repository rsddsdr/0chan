<template>
    <div>
        <Headline>
            <span slot="title">Список пользователей</span>
        </Headline>

        <div class="panel panel-default vspace" v-if="users !== null">
            <div class="panel-body">
                <div class="table-responsive">
                    <table v-if="users.length" class="table vspace">
                        <thead>
                        <tr>
                            <th>Логин</th>
                            <th>Дата регистрации</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-for="user in users">
                            <td>{{user.login}}</td>
                            <td>{{ converttime(user.create_date) }}</td>
                            <td align="right">
                                <div class="btn-group">
                                <span class="btn btn-default" @click="remove(user)">
                                    <i v-if="!user.isRemoving" class="fa fa-trash"></i>
                                    <i v-if="user.isRemoving" class="fa fa-spinner fa-spin fa-fw"></i>
                                    Удалить
                                </span>
                                </div>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="!users.length" class="empty-page">
                    <h3>Нет пользователей</h3>
                    <span class="text-muted">
                        Не пора ли пропиарить борду на сосаче?
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import Headline from '../Headline.vue';
    import User from '../../services/User'

    export default {
        components: {
            Headline
        },
        created() {
            this.fetch();
            console.log(this.users)
        },
        data() {
            return {
                users: null,

                newModeratorLogin: '',
                newModeratorIsAdmin: false,

                isAdding: false,
            }
        },
        methods: {
            fetch() {
                return User.list().then(
                    response => {
                        this.users = response.data.result;
                    }
                );
            },
            remove(user) {
                if (user.isRemoving) return;
                user.isRemoving = true;
                User.remove(user.login).then(
                    response => {
                        if (response.data.ok) {
                            return this.fetch();
                        } else if (response.data.error) {
                            mod.isRemoving = false;
                            this.$bus.emit(BusEvents.ALERT_ERROR, response.data.error);
                        }
                    }
                )
            },
            converttime(UNIX_timestamp) {
                var a = new Date(UNIX_timestamp * 1000);
                var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                var year = a.getFullYear();
                var month = months[a.getMonth()];
                var date = a.getDate();
                var hour = a.getHours();
                var min = a.getMinutes();
                var sec = a.getSeconds();
                var time = date + ' ' + month + ' ' + year + ' ' + hour + ':' + min + ':' + sec ;
                return time;
            }
        }
    }
</script>

<style lang="scss" rel="stylesheet/scss" scoped>
</style>
