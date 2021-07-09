<template>
  <div class="reviews-form" :class="{ 'is-answer' : isAnswer }" @keyup="onKeyUp">
    <form @submit.prevent="submit" enctype='multipart/form-data' ref="reviewsForm">
      <div class="form-name">{{ $parent.translate('ADD_REVIEWS') }}</div>
      <div class="form-description">
        {{ $parent.translate('REVIEWS_HELP') }}
      </div>

      <div v-for="(field, code) in fields" class="form-row raiting-row">
        <!--Оценка-->
        <div class="form-control" :id="'control-'+ code"  v-if="code == 'rang'" :class="{ 'valid-error' : field.error.length > 0 }">
          <span class="form-label">{{ field.name }}</span>
          <Raiting @on-rate="setStar"/>
          <input type="hidden" required name="rang" :value="raiting">
        </div>
        <!--Текстовое поле-->
        <div v-else-if="code == 'text'"  class="form-control" :class="{ 'valid-error' : field.error.length > 0}">
          <label class="form-label" for="review-text">{{ field.name }}</label>
          <textarea
            name="text"
            class="text-field text-area"
            id="review-text"
            :placeholder="field.placeholder"
            cols="30"
            rows="10"
            required
          ></textarea>
        </div>
        <!--Файл-->
        <div class="form-control file-control" v-else-if="field.type == 'file'" :class="{ 'valid-error' : field.error.length > 0 }">
          <div class="form-label">{{ field.name }}</div>
          <div class="custom-file">
            <input
              :type="field.type"
              multiple
              class="file-input custom-file-input"
              name="file[]"
              @change="uploadList"
              id="user-file"
              :required="field.required ? true : false"
              :placeholder="field.placeholder"
            />
            <label class="custom-file-label" for="user-file">
              <AttachSvg/>
              {{ $parent.translate('UPLOAD') }}
            </label>
          </div>
          <ul id="fileList" class="file-list"></ul>

        </div>
        <!--Все остальные поля-->
        <div class="form-control" v-else :class="{ 'valid-error' : field.error.length > 0 }">
          <label class="form-label" :for="'user-' + code">{{ field.name }}</label>
          <input
            :type="field.type"
            class="text-field"
            :name="code"
            :id="'user-' + code"
            :value="field.value.length > 0 ? field.value: null"
            :required="field.required ? true : false"
            :placeholder="field.placeholder"
          />
        </div>

      </div>
      <div class="form-row btn-row">
        <button
          class="send-btn"
          type="submit"
          :disabled="loading || timer > 0"
          name="send-form">
          {{ $parent.translate('SEND') }}
          <span class="time-cool-down" v-if="timer > 0">({{ timer }})</span>
        </button>
      </div>
    </form>
  </div>
</template>

<script>
  import Raiting from "./ReviewRaiting";
  import AttachSvg from './AttachIcon';

  export default {
    props: ['loading', 'fields', 'timer', 'item'],
    name: "reviews-form",
    data() {
      return {
        raiting: 0,
      };
    },
    components: {
      Raiting,
      AttachSvg
    },
    mounted() {
      this.$root.$on('comment-add', ()=>{ this.clearForm() });
    },
    computed: {
      isAnswer() {
        return !!this.item;
      },
    },
    methods: {
      uploadList(e) {
        const input = e.target;
        const output = input.parentNode.nextElementSibling;
        let children = "";
        for (var i = 0; i < input.files.length; ++i) {
          children +=  '<li>'+ input.files.item(i).name + '<span class="remove-list" onclick="return this.parentNode.remove()">X</span>' + '</li>'
        }
        output.innerHTML = children;
      },
      submit() {
        let formData = new FormData(event.target);
        if (this.item) {
          formData.append('parentId', this.item.commentId);
          formData.append('rootId', this.item.rootId);
          formData.append('isAnswer', 'true');
          formData.append('rang', '0');
          formData.append('parentId', this.item.commentId);
        }
        this.$root.$emit('on-reviews-submit', formData);
      },
      setStar(val) {
        this.raiting = val;
      },
      onKeyUp(e) {
        e.target.parentElement.classList.remove('valid-error');
      },
      clearForm() {
        if (this.$refs.reviewsForm)
          this.$refs.reviewsForm.reset();
      },
    },
  };
</script>

<style lang="scss">
  .reviews-form {
    #control-rang{
      font-weight: 500;
      font-size: 15px;
    }
    &.is-answer{
      #control-rang{
        display: none;
      }
      .form-name, .form-description{
        display: none;
      }
    }
  }
  .form-control {
    &.valid-error {
      input {
        border-color: red;
        box-shadow: 0px 0px 4px 1px rgba(255, 0, 0, 1);
      }
    }
  }

  .form-name {
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 6px;
  }

  .form-row {
    margin-bottom: 20px;
  }

  .form-description {
    line-height: 1.3;
    margin-bottom: 25px;
  }

  .form-label {
    display: block;
    font-weight: 500;
    font-size: 15px;
  }

  .btn-row {
    text-align: center;
    .btn {
      width: 100%;
    }
  }
  .time-cool-down{
  }
  button{
    font-family: 'TT Norms';
  }
  .send-btn {
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
    border: none;
    font-weight: 500;
    cursor: pointer;
    transition-property: background-color, color;
    transition-duration: 0.2s;
    background: #e31e24;
    color: #fff;
    width: 100%;
    max-width: 280px;
    &:hover {
      background: saturate(#e31e24, 20%);
    }
    &:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }
  }
  .text-field{
    height: 40px;
    border-radius: 2px;
    border: 1px solid #c2c7c7;
    padding-left: 24px;
    padding-right: 24px;
    width: 100%;
    font-size: 14px;
    transition: 50ms all;
    &:focus{
      box-shadow: 0px 0px 5px 0px rgba(61, 61, 236, 0.74);
    }
    &.text-area{
      padding-top: 10px;
      height: auto;
      line-height: 1.3;
      font-size: 16px;
    }
  }
  .custom-file{
    position:relative;
    font-family:arial;
    overflow:hidden;
    margin-bottom:10px;
    width: auto;
    display: inline-block;
    padding: 10px;
  }
  .custom-file-input{
    position:absolute;
    left:0;
    top:0;
    width:100%;
    height:100%;
    cursor:pointer;
    opacity:0;
    z-index:100;
  }
  .custom-file img{
    display:inline-block;
    vertical-align:middle;
    margin-right:5px;
  }
  ul.file-list{
    font-family:arial;
    list-style: none;
    padding:0;
  }
  ul.file-list li{
    border-bottom:1px solid #ddd;
    padding:5px;
  }
  .remove-list{
    cursor:pointer;
    margin-left:10px;
    font-weight: bold;
    font-size: 18px;
  }
  @media screen and (max-width: 980px) {
    .form-name {
      display: none;
    }
  }

</style>