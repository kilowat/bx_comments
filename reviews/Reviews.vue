<template>
  <div class="reviews-component">
    <div class="user-reviews">
      <div class="reviews-block">
        <div class="h-reviews">{{ translate('REVIEWS_VISITORS') }}</div>
        <div class="empty-row" v-if="!loaded">
          <span>{{ translate('LOADING') }}...</span>
        </div>
        <div class="empty-row" v-else-if="result && result.count == 0">
          <span>{{ translate('EMPTY_MESSAGES') }}</span>
        </div>
        <div class="reviews-row" v-else-if="result">
          <div class="mobile-row">
            <button class="show-form-btn" @click="showForm =!showForm">{{ translate('ADD_REVIEWS') }}</button>
            <ReviewsForm
              :loading="loading"
              :fields="fields"
              :timer="timer"
              v-if="showForm"/>
          </div>
          <div class="reviews-list" >
            <reviews-item
              v-for="item in result.comments"
              :fields="fields"
              :timer="timer"
              :item="item"
              :loading="loading"
              :isAuthAdmin="result.isAuthAdmin"
              :translate="translate"
              :key="item.commentId" />
          </div>
        </div>

      </div>
      <div class="form-block" v-if="result">
        <ReviewsForm
          :loading="loading"
          :fields="fields"
          :timer="timer"
          v-if="!isMobile"/>
      </div>
    </div>
    <MessageBox />
  </div>

</template>

<script>
import ReviewsForm from "./ReviewsForm";
import MessageBox from "./MessageBox";
import ReviewsItem from './ReviewsItem';
export default {
  props: ['paramsId', 'jsonParams'],
  name: "reviews",
  data() {
    return {
      loading: false,
      loaded: false,
      mobileWidth: 980,
      isMobile: false,
      result: null,
      fields: null,
      t: false,
      timer: 0,
      lang: null,
      showForm: false,
    };
  },
  async mounted() {
    this.isMobile = this.mobileWidth >= window.innerWidth;

    window.addEventListener("resize", this.resizeEventHandler);

    await this.fetchData();

    let hash = location.hash;
    if (hash.match(/comment/)) {
      let foundNode = document.querySelector(hash);
      if (foundNode) {
        foundNode.classList.add('selected');
      }
    }

    this.$root.$on('on-reviews-submit', (data)=>{ this.onSubmit(data) });
  },
  destroyed() {
    window.removeEventListener("resize", this.resizeEventHandler);
  },
  components: {
    ReviewsItem,
    ReviewsForm,
    MessageBox
  },
  computed: {
    params() {
      return JSON.parse(this.jsonParams);
    }
  },
  methods: {
    translate(code) {
      if (this.lang && this.lang[code])
        return this.lang[code];
      else
        return code;
    },
    setTimerCoolDown() {
      if (!this.t) {
        this.t = setInterval(()=>{
          if (this.timer <=0) {
            clearTimeout(this.t);
            this.t = false;
            this.timer = 0;
          } else {
            this.timer--;
            if (this.timer <=0) this.timer = 0;
          }
        }, 1000)
      }
    },
    resizeEventHandler(e) {
      this.isMobile = this.mobileWidth >= window.innerWidth;
    },
    async fetchData() {
      try {
        this.loading = true;
        let params = new FormData();
        params.append('params_id', this.paramsId);
        let response = await fetch(this.getApiUrl('data'), {
          method: 'POST',
          body: params
        });
        this.loaded = true;
        if (response.ok) {
          let json = await response.json();
          if (json.status === 'success')
            this.result = json.data;
            this.fields = json.data.fields;
            this.timer = json.data.timer;
            this.lang = json.data.lang;
            if(this.timer > 0) {
              this.setTimerCoolDown();
            }
        } else {
          //alert("Ошибка HTTP: " + response.status);
        }
      } catch (e) {
        console.log(e);
      }finally {
        this.loading = false;
      }
    },
    onSubmit(data) {
      this.add(data);
    },
    async add(data) {
      data.append('params_id', this.paramsId);
      data.append('elementId', this.params.ELEMENT_ID);

      try {
        this.loading = true;
        let response = await fetch(this.getApiUrl('add'), {
          method: 'POST',
          body: data,
        });
        this.loaded = true;
        if (response.ok) {
          let json = await response.json();
          if (json.status === 'success') {
            const fields = json.data.fields;
            this.result = json.data;
            this.timer = json.data.timer;
            this.showMessage({ type: 'success', text: json.data.msg});
            this.setTimerCoolDown();

            for (let k in fields) {
              this.$set(this.fields[k], 'value',  fields[k].value);
            }
            this.afterAdd(json.data.created);
          } else {
            const fields = json.data.fields;

            for (let k in fields) {
              this.$set(this.fields[k], 'error',  fields[k].error);
              this.$set(this.fields[k], 'value',  fields[k].value);
            }
            this.showErrors(json.errors);
          }
        } else {
          this.showErrors('Ошибка на сервере');
        }
      } catch (e) {
        console.log(e);
      } finally {
        this.loading = false;
      }
    },
    getApiUrl(action) {
      return  `/bitrix/services/main/ajax.php?c=extend.mode:reviews&action=${action}&mode=class`;
    },
    showErrors(errors) {
      let message = '<ul>';
      for (let i in errors) {
        if (message.code === 0) {
          this.showMessage({ type: 'error', text: 'При добавлении отзыва произошла ошибка'});
          return;
        }
        message += '<li>' + errors[i].message + '</li>';
      }
      message += '</ul>';
      this.showMessage({ type: 'error', text: message});
    },
    showMessage(message) {
      this.$root.$emit('show-reviews-message', message);
    },
    updateNodeCount() {
      const countNode = document.querySelector('#c-count');
      if (countNode) {
        countNode.innerText = this.result.count;
      }
    },
    afterAdd(created) {
      this.updateNodeCount();
      this.$nextTick(()=>{
        const id = created.id;
        const commentEl = document.querySelector('#comment-'+id);
        if(commentEl) {
          commentEl.classList.add('new-comment');
          setTimeout(()=>{
            commentEl.classList.remove('new-comment');
          }, 6000)
        }
      });
      this.$root.$emit('comment-add', created);
    }
  },
};
</script>

<style lang="scss" scoped>
.user-reviews {
  display: flex;
  flex-wrap: wrap;
  justify-content: space-between;
}
.reviews-block {
  width: calc(100% - 300px);
  max-width: 650px;
}
.form-block {
  width: 290px;
  margin-left: 10px;
}
.h-reviews {
  font-size: 18px;
  font-weight: bold;
  margin-bottom: 28px;
}
.empty-row {
  font-size: 16px;
  margin-bottom: 20px;
  span {
    margin-right: 25px;
  }
}
.mobile-row{
  display: none;
  margin-bottom: 16px;
  text-align: center;
}
.show-form-btn{
  text-decoration: none;
  border-radius: 6px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding-left: 1em;
  padding-right: 1em;
  padding-top: 0.82em;
  padding-bottom: 0.82em;
  box-shadow: none;
  font-weight: 500;
  cursor: pointer;
  transition-property: background-color, color;
  transition-duration: 0.2s;
  background: transparent;
  color: #000;
  width: 100%;
  max-width: 280px;
  border: 1px solid #000;
  margin-bottom: 12px;
  &:hover {
    background: saturate(#e31e24, 20%);
    color: #fff;
  }
  &:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
}
@media screen and (max-width: 980px) {
  .form-block {
    width: 0;
    margin-left: 0;
  }
  .reviews-block {
    width: 100%;
    max-width: 100%;
  }
  .mobile-row{
    display: block;
  }
}
</style>