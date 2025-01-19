<template>
	<div class="site-manage">
        <Headline>
            <span slot="title">
                Глобальные настройки сайта
            </span>
        </Headline>

        <div class="row">
            <div class="col-md-12">
                <form @submit.prevent="saveSettings">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <b>Настройки сайта</b>
                        </div>
                        <div class="panel-body">
                            <div class="form-horizontal">
                                <FormBuilder :form="form" :data="settings_data" />
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
        </div>
    </div>
</template>

<script>
	import Headline from '../Headline.vue'
	import BusEvents from '../../app/BusEvents';
	import Globals from '../../services/Globals'
    import FormBuilder from '../FormBuilder.vue';

	export default {
        components: {
            Headline,
            FormBuilder
        },
        created() {

        },
        data() {
        	return {
        		settings_data: null,
                form: null
        	}
        },
        methods: {
        	fetch() {
				return Globals.settings().then(
                    response => {
                        if (response.data.ok) {
                            this.settings_data = response.data.result;
                            this.form = response.data.form;
                        }
                    }
                );
        	},
            saveSettings() {
                Globals.save(this.settings_data).then(
                    response => {
                        if (response.data.ok) {
                            this.$bus.emit(BusEvents.ALERT_SUCCESS, 'Настройки сохранены');
                        } else {
                            if (response.data.errors) {
                                response.data.errors.forEach(element => this.$bus.emit(BusEvents.ALERT_ERROR, element));
                            }
                        }
                    }
                );
            }
        }
	}
	
</script>

<style>
    .site-manage {
        .panel { position: relative }
        .alert { position: absolute; top: 10px; right: 10px }
    }
</style>