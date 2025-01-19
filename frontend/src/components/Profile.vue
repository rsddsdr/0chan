<template>
    <div class="profile-page">
        <Headline>
            <span slot="title">
                Аккаунт и настройки
            </span>
        </Headline>

        <div v-if="user" style="margin-top: 10px">
            <div class="row">
                <div class="col-md-12">
                    <form @submit.prevent="saveSettings">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <b>Аккаунт: {{user.login}}</b>
                            </div>
                            <div class="panel-body">
                                <div class="form-horizontal">
                                    <FormBuilder :form="form" :data="user" />
                                </div>
                            </div>
                            <div class="panel-footer text-right">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-gears"></i> Сохранить
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="col-md-12">
                    <form @submit.prevent="changePassword">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <b>Сменить пароль</b>
                            </div>
                            <div class="panel-body">
                                <div class="form-horizontal">
                                    <div class="form-group" :class="{'has-error': passwordChangeFormErrors.oldPassword}">
                                        <label class="control-label col-md-8">Старый пароль</label>
                                        <div class="col-md-12">
                                            <input type="password" class="form-control" v-model="passwordChangeForm.oldPassword" />
                                        </div>
                                    </div>
                                    <div class="form-group" :class="{'has-error': passwordChangeFormErrors.newPassword}">
                                        <label class="control-label col-md-8">Новый пароль</label>
                                        <div class="col-md-12">
                                            <input type="password" class="form-control" v-model="passwordChangeForm.newPassword" />
                                        </div>
                                    </div>
                                    <div class="form-group" :class="{'has-error': passwordChangeFormErrors.newRepeated}">
                                        <label class="control-label col-md-8">Повтор</label>
                                        <div class="col-md-12">
                                            <input type="password" class="form-control" v-model="passwordChangeForm.newRepeated" />
                                        </div>
                                    </div>
                                    <ul class="has-error col-md-offset-2">
                                        <li class="help-block" v-for="error in passwordChangeFormErrors">{{error}}</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="panel-footer text-right">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-lock"></i> Сменить
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="col-md-12">
                    <form @submit.prevent="createInvite">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <b>Инвайты</b>
                            </div>
                            <div class="panel-body">
                                <div class="form-horizontal">
                                    <span v-if="invites.length == 0"><center><b>- Вы не создавали ещё инвайтов -</b></center></span>
                                    <tbody>
                                        <tr v-for="invite in invites">
                                            <td>
                                                <span class="im-address" v-clipboard="makeLink(invite.invite)" @success="inviteCopied">{{invite.invite}}</span>
                                            </td>

                                            <td width="100%">
                                                <div class="pull-right">
                                                    <span v-if="invite.expired">Истёк</span>
                                                    <span v-if="invite.used">Использован: {{ invite.usedBy }}</span>
                                                    <a class="im-button btn-default fa fa-clipboard"  v-clipboard="makeLink(invite.invite)" @success="inviteCopied"></a>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </div>
                            </div>
                            <div class="panel-footer text-right">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-plus"></i> Создать
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="col-md-12">
                    <form @submit.prevent="saveCss">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <b>Собственные стили (CSS)</b>
                            </div>
                            <div class="panel-body">
                                <div class="form-horizontal">
                                    <textarea class="form-control" v-model="custom_css" rows="10"></textarea>
                                </div>
                            </div>
                            <div class="panel-footer text-right">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-paint-brush"></i> Сохранить
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</template>

<script>
    import BusEvents from '../app/BusEvents';
    import User from '../services/User';
    import Session from '../services/Session';
    import Headline from './Headline.vue';
    import FormBuilder from './FormBuilder.vue';
    import Invite from '../services/Invite';

    export default {
        components: {
            Headline, FormBuilder
        },
        data() {
            return {
                user: null,
                invites: null,
                custom_css: '',
                passwordChangeForm: {
                    oldPassword: '',
                    newPassword: '',
                    newRepeated: ''
                },
                passwordChangeFormErrors: {
                    oldPassword: null,
                    newPassword: null,
                    newRepeated: null
                }
            }
        },
        methods: {
            fetch() {
                Invite.get().then(
                    response => {
                        if (response.data.ok) {
                            this.invites = response.data.invites;
                        }
                    }
                )
                return User.get().then(
                    response => {
                        if (response.data.ok) {
                            this.user = response.data.user;
                            this.custom_css = this.user.customCss;

                            if (this.custom_css) {
                                localStorage.custom_css = this.custom_css;
                                $('.user-css').remove();
                                var ls = $('style').last();
                                ls.after($("<style></style>")
                                    .addClass("user-css")
                                    .text(localStorage.custom_css)
                                );
                            }

                            this.form = response.data.form;
                        }
                    }
                )
            },
            saveSettings() {
                User.save(this.user).then(
                    response => {
                        if (response.data.ok) {
                            this.$bus.emit(BusEvents.ALERT_SUCCESS, 'Настройки сохранены');
                            this.$bus.emit(BusEvents.REFRESH_SIDEBAR);
                            Session.checker.checkNow();
                        }
                    }
                )
            },
            saveCss() {
                User.set_style(this.custom_css).then(
                    response => {
                        if (response.data.ok) {
                            this.$bus.emit(BusEvents.ALERT_SUCCESS, "Стили сохранены!");
                            localStorage.custom_css = this.custom_css;
                            $('.user-css').remove();
                            var ls = $('style').last();
                            ls.after($("<style></style>")
                                .addClass("user-css")
                                .text(localStorage.custom_css)
                            );
                        } else {
                            this.$bus.emit(BusEvents.ALERT_ERROR, response.data.error);
                        }
                    }
                )
            },
            createInvite() {
                Invite.create().then(
                    response => {
                        if (response.data.ok) {
                            if (this.invites === null) {
                                this.invites = [response.data.result];
                            } else {
                                this.invites.push(response.data.result);
                            }
                            this.$bus.emit(BusEvents.ALERT_SUCCESS, "Инвайт создан");
                        } else {
                            if (response.data.result == 'limit_exceeded') {
                                this.$bus.emit(BusEvents.ALERT_ERROR, "Вы исчерпали лимит в 3 инвайта для этого аккаунта, если вы хотите создать больше инвайтов обратитесь к администратору.");
                            } 
                        }
                    }
                )
            },
            makeLink(invite) {
                const route = this.$router.resolve({ name: 'login_with_invite', params: { 'invite': invite } });
                return window.location.protocol + '//' + window.location.host + route.href;
            },
            changePassword() {
                this.passwordChangeFormErrors = {};
                if (this.passwordChangeForm.newPassword != this.passwordChangeForm.newRepeated) {
                    this.passwordChangeFormErrors.newRepeated = 'Пароли не совпадают';
                    return
                }
                User.changePassword(this.passwordChangeForm.oldPassword, this.passwordChangeForm.newPassword).then(
                    response => {
                        if (response.data.ok) {
                            this.passwordChangeFormErrors = {};
                            this.$bus.emit(BusEvents.ALERT_SUCCESS, 'Пароль изменён');
                            this.passwordChangeForm = {
                                oldPassword: '',
                                newPassword: '',
                                newRepeated: ''
                            };
                            setTimeout(() => this.passwordChanged = false, 3000);
                        } else {
                            this.passwordChangeFormErrors = response.data['form-errors'];
                        }
                    }
                )
            },
            inviteCopied() {
                this.$bus.emit(BusEvents.ALERT_INFO, 'Инвайт скопирован в буфер');
            }
        }
    }
</script>


<style lang="scss" rel="stylesheet/scss">
    @import '~assets/styles/_vars';

    .profile-page {
        .panel { position: relative }
        .alert { position: absolute; top: 10px; right: 10px }
    }
    .im-address {
        cursor: pointer;
    }

    .im-button {
        display: inline-block;
        text-align: center;
        color: $color-grey-lt !important;
        &:hover {
            background: none !important;
            color: $color-green !important;
        }
        width: 32px;
    }
</style>